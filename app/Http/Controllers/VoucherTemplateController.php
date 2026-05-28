<?php

namespace App\Http\Controllers;

use App\Models\VoucherTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VoucherTemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $templates = VoucherTemplate::orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();
        
        // Get router from session or first active router
        $router = \App\Models\Router::where('is_active', true)->first();
        $routerId = $router ? $router->id : 1;
        
        return view('voucher-templates.index', compact('templates', 'routerId'));
    }

    /**
     * Show template editor (similar to mikhmon)
     */
    public function editor(Request $request, $router = null)
    {
        $templateType = $request->get('template', 'default'); // default, small, thermal
        
        // Get router ID from route parameter or query string
        if ($router) {
            // If router is provided as route parameter (from /hotspot/{router}/template)
            if (is_numeric($router)) {
                $routerId = (int)$router;
            } elseif (is_object($router) && isset($router->id)) {
                $routerId = $router->id;
            } else {
                $routerId = $request->get('router', 1);
            }
        } else {
            // Get from query string (from /voucher-templates/editor?router=1)
            $routerId = $request->get('router', 1);
        }
        
        // IMPORTANT: Always get fresh template from database (bypass cache)
        // Get template using same logic as print voucher (ensures consistency)
        $template = $this->getVoucherTemplateContent($routerId, $templateType);
        
        // If still no template, create empty one
        if (!$template) {
            $template = new VoucherTemplate([
                'name' => $templateType,
                'html_content' => '',
                'is_default' => $templateType == 'default'
            ]);
        } else {
            // Force refresh to get latest data from database
            $template->refresh();
            \Log::debug("Editor loaded template: name={$templateType}, id={$template->id}, content_length=" . strlen($template->html_content ?? ''));
        }
        
        $router = \App\Models\Router::find($routerId);
        
        return view('voucher-templates.editor', compact('template', 'templateType', 'routerId', 'router'));
    }

    /**
     * Save template from editor
     */
    public function saveFromEditor(Request $request)
    {
        $request->validate([
            'template' => 'required|string',
            'html_content' => 'required|string',
            'router' => 'nullable|integer',
        ]);
        
        $templateType = $request->template;
        $htmlContent = $request->html_content;
        
        // Log received data for debugging
        \Log::info("Save template request: template={$templateType}, router=" . $request->router . ", content_length=" . strlen($htmlContent));
        \Log::debug("Template content preview: " . substr($htmlContent, 0, 200));
        
        // IMPORTANT: Update or create template (template yang diedit via web)
        // This MUST save to database so it persists after reload
        // Check if template exists
        $existing = VoucherTemplate::where('name', $templateType)->first();
        
        if ($existing) {
            // Update existing
            $existing->html_content = $htmlContent;
            $existing->is_default = $templateType == 'default';
            $existing->save();
            $existing->refresh();
            $template = $existing;
        } else {
            // Create new
            $template = VoucherTemplate::create([
                'name' => $templateType,
                'html_content' => $htmlContent,
                'is_default' => $templateType == 'default'
            ]);
        }
        
        // Force refresh to ensure latest data
        $template->refresh();
        
        // Verify save by reading back from database directly (bypass model cache)
        $saved = VoucherTemplate::where('name', $templateType)->first();
        if (!$saved || strlen($saved->html_content ?? '') != strlen($htmlContent)) {
            \Log::error("Template save verification FAILED! Expected length: " . strlen($htmlContent) . ", Got: " . strlen($saved->html_content ?? ''));
            // Try direct DB update as last resort
            \DB::table('voucher_templates')
                ->where('name', $templateType)
                ->update(['html_content' => $htmlContent]);
            $saved = VoucherTemplate::where('name', $templateType)->first();
            $saved->refresh();
        }
        
        \Log::info("Template saved SUCCESS: name={$templateType}, id={$template->id}, content_length=" . strlen($htmlContent) . ", verified_length=" . strlen($saved->html_content ?? '') . ", contains_testing=" . (strpos($htmlContent, 'TESTING') !== false ? 'YES' : 'NO'));
        
        // If setting as default, unset others
        if ($template->is_default) {
            VoucherTemplate::where('name', '!=', $templateType)->update(['is_default' => false]);
        }
        
        // Redirect back - use hotspot.template route if router is provided, otherwise use voucher-templates.editor
        if ($request->has('router') && $request->router) {
            // Use hotspot.template route if router is provided (from /hotspot/{router}/template)
            $redirectUrl = route('hotspot.template', ['router' => $request->router]) . '?template=' . $templateType;
        } else {
            // Fallback to voucher-templates.editor route
            $redirectUrl = route('voucher-templates.editor', ['template' => $templateType]);
            if ($request->has('router')) {
                $redirectUrl .= '&router=' . $request->router;
            }
        }
        
        \Log::info("Redirecting to: {$redirectUrl}");
        
        if ($request->has('preview')) {
            return redirect($redirectUrl)->with('success', 'Template berhasil disimpan! Preview template...');
        }
        
        return redirect($redirectUrl)->with('success', 'Template berhasil disimpan! Template ini akan digunakan saat print voucher.');
    }

    /**
     * Reset template to default
     */
    public function resetTemplate(Request $request)
    {
        $templateType = $request->get('template', 'default');
        $routerId = $request->get('router', 1);
        
        $defaultContent = $this->getDefaultTemplateContent($templateType);
        
        $template = VoucherTemplate::updateOrCreate(
            ['name' => $templateType],
            [
                'html_content' => $defaultContent,
                'is_default' => $templateType == 'default'
            ]
        );
        
        $redirectUrl = route('voucher-templates.editor', ['template' => $templateType]);
        if ($routerId) {
            $redirectUrl .= '&router=' . $routerId;
        }
        
        return redirect($redirectUrl)->with('success', 'Template direset ke default!');
    }

    /**
     * Get voucher template (same logic as HotspotController)
     * This ensures editor, preview, and print voucher use the same template
     */
    private function getVoucherTemplateContent($routerId, $templateType = 'default')
    {
        // Priority 1: Get from database (template yang sudah diedit via web)
        // IMPORTANT: Always get fresh from database (no cache) to ensure latest changes are used
        try {
            // Try to get by name first (default, small, etc)
            // Use fresh() to bypass query cache and get latest data
            $template = VoucherTemplate::where('name', $templateType)->first();
            if ($template && !empty(trim($template->html_content ?? ''))) {
                // Refresh to ensure we have latest data
                $template->refresh();
                \Log::debug("Editor: Using template from database: name={$templateType}, id={$template->id}, content_length=" . strlen($template->html_content));
                return $template;
            }
            
            // If not found by name, get default template
            $template = VoucherTemplate::where('is_default', true)->first();
            if ($template && !empty(trim($template->html_content ?? ''))) {
                // Refresh to ensure we have latest data
                $template->refresh();
                \Log::debug("Editor: Using default template from database: id={$template->id}, content_length=" . strlen($template->html_content));
                return $template;
            }
        } catch (\Exception $e) {
            \Log::debug("Failed to get template from database: " . $e->getMessage());
        }
        
        // Priority 2: Try to fetch from external URL
        $externalUrl = "https://raw.kitabill.site/hotspot/{$routerId}/template";
        try {
            $client = new \GuzzleHttp\Client(['timeout' => 5, 'verify' => false]);
            $response = $client->get($externalUrl);
            if ($response->getStatusCode() === 200) {
                $content = (string) $response->getBody();
                // Check if it's HTML template (not redirect page)
                if (strpos($content, '<!DOCTYPE') === false && strpos($content, 'Redirecting') === false) {
                    if (strpos($content, '<table') !== false || strpos($content, '<?=') !== false || strpos($content, '<?php') !== false) {
                        // Save to database for next time
                        try {
                            $template = VoucherTemplate::updateOrCreate(
                                ['name' => $templateType],
                                ['html_content' => $content, 'is_default' => $templateType == 'default']
                            );
                            return $template;
                        } catch (\Exception $e) {
                            // Ignore save error
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::debug("Failed to fetch template from external URL: " . $e->getMessage());
        }
        
        // Priority 3: Create default template from mikhmon and save to database
        try {
            $defaultContent = $this->getDefaultTemplateContent($templateType);
            if (!empty($defaultContent)) {
                $template = VoucherTemplate::updateOrCreate(
                    ['name' => $templateType],
                    ['html_content' => $defaultContent, 'is_default' => $templateType == 'default']
                );
                return $template;
            }
        } catch (\Exception $e) {
            \Log::debug("Failed to load default template: " . $e->getMessage());
        }
        
        // Fallback: return null
        return null;
    }

    /**
     * Preview template (like mikhmon vpreview.php)
     */
    public function preview(Request $request)
    {
        $templateType = $request->get('template', 'default');
        $qr = $request->get('qr', 'no');
        $small = $request->get('small', 'no');
        $usermode = $request->get('usermode', 'up'); // up or vc
        $routerId = $request->get('router', 1);
        
        // Get template using same logic as print voucher (ensures preview = print)
        $template = $this->getVoucherTemplateContent($routerId, $templateType);
        
        // If no template found, create one with default content for preview
        if (!$template) {
            $defaultContent = $this->getDefaultTemplateContent($templateType);
            $template = new VoucherTemplate([
                'name' => $templateType,
                'html_content' => $defaultContent ?: '',
            ]);
        }
        
        // Get logo from settings
        $logoPath = \App\Models\Setting::get('company_logo');
        $logo = asset('img/logo.png');
        if ($logoPath && \Storage::disk('public')->exists($logoPath)) {
            $logo = \Storage::disk('public')->url($logoPath);
        }
        
        // Get router
        $router = \App\Models\Router::find($routerId);
        $dnsname = $router ? ($router->host ?? 'hotspot.local') : 'hotspot.local';
        $hotspotname = \App\Models\Setting::get('company_name', 'Hotspot');
        
        // Sample data for preview (like mikhmon vpreview.php)
        $username = "mikhmon";
        $password = $usermode == 'vc' ? "mikhmon" : "1234";
        $profile = "default";
        $timelimit = "6h";
        $getdatalimit = 1073741824; // 1GB
        $comment = "test";
        $validity = "1d";
        
        // Format bytes
        function formatBytes($size, $decimals = 0){
            $unit = array('0' => 'Byte', '1' => 'KiB', '2' => 'MiB', '3' => 'GiB', '4' => 'TiB', '5' => 'PiB', '6' => 'EiB', '7' => 'ZiB', '8' => 'YiB');
            for($i = 0; $size >= 1024 && $i <= count($unit); $i++){
                $size = $size/1024;
            }
            return round($size, $decimals).' '.$unit[$i];
        }
        
        if ($getdatalimit == 0) {
            $datalimit = "";
        } else {
            $datalimit = formatBytes($getdatalimit, 2);
        }
        
        // Price
        $currency = \App\Models\Setting::get('currency', 'Rp');
        $getprice = "5000";
        if (in_array($currency, ['Rp', 'IDR'])) {
            $price = $currency . " " . number_format((float)$getprice, 0, ",", ".");
        } else {
            $price = $currency . " " . number_format((float)$getprice, 2);
        }
        
        $urilogin = "http://{$dnsname}/login?username={$username}&password={$password}";
        $uid = 'preview_qr';
        $num = 1;
        
        // QR Code HTML
        $qrcode = '';
        if ($qr == 'yes') {
            $qrcode = "
	<canvas class='qrcode' id='{$uid}'></canvas>
    <script>
      (function() {
        var {$uid} = new QRious({
          element: document.getElementById('{$uid}'),
          value: '{$urilogin}',
          size:'256'
        });
      })();
    </script>
	";
        }
        
        // Determine user mode for preview
        $usermode = ($username === $password) ? 'vc' : 'up';
        
        return view('voucher-templates.preview', compact(
            'template',
            'logo',
            'hotspotname',
            'username',
            'password',
            'profile',
            'timelimit',
            'datalimit',
            'comment',
            'validity',
            'price',
            'dnsname',
            'qrcode',
            'num',
            'usermode',
            'qr',
            'small',
            'uid',
            'urilogin'
        ));
    }

    /**
     * Get default template content from mikhmon template
     */
    private function getDefaultTemplateContent($templateType = 'default')
    {
        // Use mikhmon template as default (PHP format, not blade)
        $mikhmonPath = base_path('../html/mikhmon/voucher/template.php');
        if ($templateType == 'small' && file_exists(base_path('../html/mikhmon/voucher/template-small.php'))) {
            $mikhmonPath = base_path('../html/mikhmon/voucher/template-small.php');
        }
        
        if (file_exists($mikhmonPath)) {
            return file_get_contents($mikhmonPath);
        }
        
        // Fallback: convert blade to PHP if mikhmon not found
        if ($templateType == 'small') {
            $file = resource_path('views/hotspot/voucher/template-small.blade.php');
        } else {
            $file = resource_path('views/hotspot/voucher/template.blade.php');
        }
        
        if (file_exists($file)) {
            $content = file_get_contents($file);
            // Convert blade syntax to PHP for template
            $content = str_replace('{{ ', '<?= ', $content);
            $content = str_replace(' }}', '; ?>', $content);
            $content = str_replace('@if', '<?php if', $content);
            $content = str_replace('@elseif', '<?php } elseif', $content);
            $content = str_replace('@else', '<?php } else {', $content);
            $content = str_replace('@endif', '<?php }', $content);
            $content = str_replace('$logoUrl', '$logo', $content);
            return $content;
        }
        
        return '';
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('voucher-templates.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:voucher_templates,name',
            'html_content' => 'required|string',
            'css_content' => 'nullable|string',
            'is_default' => 'nullable|boolean',
        ]);

        // Jika set sebagai default, unset yang lain
        if ($request->has('is_default') && $request->is_default) {
            VoucherTemplate::where('is_default', true)->update(['is_default' => false]);
        }

        VoucherTemplate::create($validated);

        return redirect()->route('voucher-templates.index')
            ->with('success', 'Template voucher berhasil dibuat!');
    }

    /**
     * Display the specified resource.
     */
    public function show(VoucherTemplate $voucherTemplate)
    {
        return view('voucher-templates.show', compact('voucherTemplate'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(VoucherTemplate $voucherTemplate)
    {
        return view('voucher-templates.edit', compact('voucherTemplate'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, VoucherTemplate $voucherTemplate)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:voucher_templates,name,' . $voucherTemplate->id,
            'html_content' => 'required|string',
            'css_content' => 'nullable|string',
            'is_default' => 'nullable|boolean',
        ]);

        // Jika set sebagai default, unset yang lain
        if ($request->has('is_default') && $request->is_default) {
            VoucherTemplate::where('is_default', true)
                ->where('id', '!=', $voucherTemplate->id)
                ->update(['is_default' => false]);
        }

        $voucherTemplate->update($validated);

        return redirect()->route('voucher-templates.index')
            ->with('success', 'Template voucher berhasil diupdate!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(VoucherTemplate $voucherTemplate)
    {
        // Jangan hapus jika ini adalah default template
        if ($voucherTemplate->is_default) {
            return redirect()->back()
                ->with('error', 'Tidak bisa menghapus template default! Set template lain sebagai default terlebih dahulu.');
        }

        $voucherTemplate->delete();

        return redirect()->route('voucher-templates.index')
            ->with('success', 'Template voucher berhasil dihapus!');
    }

    /**
     * Set template as default
     */
    public function setDefault(VoucherTemplate $voucherTemplate)
    {
        DB::transaction(function () use ($voucherTemplate) {
            VoucherTemplate::where('is_default', true)->update(['is_default' => false]);
            $voucherTemplate->update(['is_default' => true]);
        });

        return redirect()->back()
            ->with('success', 'Template di-set sebagai default!');
    }
}
