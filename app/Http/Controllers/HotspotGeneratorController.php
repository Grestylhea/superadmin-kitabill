<?php

namespace App\Http\Controllers;

use App\Models\Router;
use App\Models\HotspotUser;
use App\Models\HotspotProfile;
use App\Models\Setting;
use App\Models\VoucherTemplate;
use App\Services\MikrotikService;
use Illuminate\Http\Request;

class HotspotGeneratorController extends Controller
{
    /**
     * Quick print - print voucher immediately
     * Uses the same template as printVoucher
     */
    public function quickPrint(Request $request, $router)
    {
        try {
            $router = Router::findOrFail($router);
            
            // Get parameters
            $comment = $request->get('comment'); // Comment filter for batch vouchers
            $qr = $request->get('qr', 'no');
            $small = $request->get('small', 'no');
            
            if (!$comment) {
                return back()->with('error', 'Comment diperlukan untuk quick print!');
            }
            
            // Get users by comment
            $hotspotUsers = HotspotUser::where('router_id', $router->id)
                ->where('comment', 'like', "%{$comment}%")
                ->get();
            
            if ($hotspotUsers->isEmpty()) {
                return back()->with('error', 'Tidak ada voucher dengan comment tersebut!');
            }

            // Get logo from settings
            $logoPath = Setting::get('company_logo');
            $logoUrl = asset('img/logo.png');
            if ($logoPath && \Storage::disk('public')->exists($logoPath)) {
                $logoUrl = \Storage::disk('public')->url($logoPath);
            }
            
            // Get router DNS name
            $dnsname = $router->host ?? 'hotspot.local';
            try {
                $service = new MikrotikService($router);
                $servers = $service->getHotspotServers();
                if (!empty($servers) && isset($servers[0]['dns-name'])) {
                    $dnsname = $servers[0]['dns-name'];
                }
            } catch (\Exception $e) {
                \Log::debug("Could not get DNS name from MikroTik: " . $e->getMessage());
            }
            
            // Get hotspot name from settings
            $hotspotname = Setting::get('company_name', $router->name ?? 'Hotspot');
            
            // IMPORTANT: Get template from database (same as printVoucher and editor)
            // This ensures quick print uses the same template as editor
            // MUST use the same method as printVoucher to ensure consistency
            $template = $this->getVoucherTemplate($router->id, $small == 'yes' ? 'small' : 'default');
            
            // Log template info for debugging
            \Log::info("QuickPrint: Template loaded, length=" . strlen($template ?? '') . ", has_testing=" . ($template && strpos($template, 'TESTING') !== false ? 'YES' : 'NO'));
            
            return view('hotspot.voucher.print', compact(
                'hotspotUsers',
                'logoUrl',
                'dnsname',
                'hotspotname',
                'qr',
                'small',
                'router',
                'template'
            ));
        } catch (\Exception $e) {
            \Log::error("Error in quickPrint: " . $e->getMessage());
            return back()->with('error', 'Gagal quick print: ' . $e->getMessage());
        }
    }

    /**
     * Get voucher template from database (same logic as HotspotController)
     */
    private function getVoucherTemplate($routerId, $templateType = 'default')
    {
        // Priority 1: Get from database (template yang sudah diedit via web)
        // IMPORTANT: Always get FRESH from database (no cache) to ensure latest changes are used
        try {
            // Try to get by name first (default, small, etc)
            // Use fresh query to bypass any cache
            $template = VoucherTemplate::where('name', $templateType)->first();
            if ($template) {
                // Force refresh to get latest data
                $template->refresh();
                if (!empty(trim($template->html_content ?? ''))) {
                    \Log::info("QuickPrint: Using template from database: name={$templateType}, id={$template->id}, content_length=" . strlen($template->html_content) . ", has_testing=" . (strpos($template->html_content, 'TESTING') !== false ? 'YES' : 'NO'));
                    return $template->html_content;
                }
            }
            
            // If not found by name, get default template
            $template = VoucherTemplate::where('is_default', true)->first();
            if ($template) {
                $template->refresh();
                if (!empty(trim($template->html_content ?? ''))) {
                    \Log::info("QuickPrint: Using default template from database: id={$template->id}, content_length=" . strlen($template->html_content) . ", has_testing=" . (strpos($template->html_content, 'TESTING') !== false ? 'YES' : 'NO'));
                    return $template->html_content;
                }
            }
        } catch (\Exception $e) {
            \Log::error("Failed to get template from database: " . $e->getMessage());
        }
        
        // Priority 2: Try to fetch from external URL (ONLY if no template in database)
        // IMPORTANT: Do NOT override database template with external URL
        // Only use external URL if database is empty
        $externalUrl = "https://raw.kitabill.site/hotspot/{$routerId}/template";
        try {
            $client = new \GuzzleHttp\Client(['timeout' => 5, 'verify' => false]);
            $response = $client->get($externalUrl);
            if ($response->getStatusCode() === 200) {
                $content = (string) $response->getBody();
                if (strpos($content, '<!DOCTYPE') === false && strpos($content, 'Redirecting') === false) {
                    if (strpos($content, '<table') !== false || strpos($content, '<?=') !== false || strpos($content, '<?php') !== false) {
                        // Only save to database if template doesn't exist yet (don't override edited template)
                        try {
                            $existing = VoucherTemplate::where('name', $templateType)->first();
                            if (!$existing || empty(trim($existing->html_content ?? ''))) {
                                VoucherTemplate::updateOrCreate(
                                    ['name' => $templateType],
                                    ['html_content' => $content, 'is_default' => $templateType == 'default']
                                );
                                \Log::info("QuickPrint: Loaded from external URL and saved (no existing template)");
                                return $content;
                            } else {
                                \Log::info("QuickPrint: Skipped external URL - template exists in database");
                                // Return database template instead
                                return $existing->html_content;
                            }
                        } catch (\Exception $e) {
                            \Log::error("Error saving external template: " . $e->getMessage());
                        }
                        return $content;
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::debug("Failed to fetch template from external URL: " . $e->getMessage());
        }
        
        // Priority 3: Create default template from mikhmon and save to database
        try {
            $mikhmonPath = base_path('../html/mikhmon/voucher/template.php');
            if ($templateType == 'small' && file_exists(base_path('../html/mikhmon/voucher/template-small.php'))) {
                $mikhmonPath = base_path('../html/mikhmon/voucher/template-small.php');
            }
            
            if (file_exists($mikhmonPath)) {
                $content = file_get_contents($mikhmonPath);
                // Save to database
                VoucherTemplate::updateOrCreate(
                    ['name' => $templateType],
                    ['html_content' => $content, 'is_default' => $templateType == 'default']
                );
                return $content;
            }
        } catch (\Exception $e) {
            \Log::debug("Failed to load default template: " . $e->getMessage());
        }
        
        // Fallback: return null to use blade template
        return null;
    }

    // Placeholder methods for other routes
    public function index(Request $request, $router)
    {
        // Generator index page
        return redirect()->route('hotspot.generate', $router);
    }

    public function generate(Request $request, $router)
    {
        // Generate vouchers
        // Implementation here
        return back()->with('success', 'Voucher generated');
    }

    public function export(Request $request, $router)
    {
        // Export vouchers
        return back()->with('success', 'Voucher exported');
    }
}

