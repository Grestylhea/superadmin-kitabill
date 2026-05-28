<!DOCTYPE html>
<html>
<head>
    <title>Voucher - {{ $hotspotname }}</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta http-equiv="pragma" content="no-cache" />
    <script src="{{ asset('js/qrious.min.js') }}"></script>
    <style>
body {
  color: #000000;
  background-color: #FFFFFF;
  font-size: 14px;
  font-family: 'Helvetica', arial, sans-serif;
  margin: 0px;
  -webkit-print-color-adjust: exact;
}
table.voucher {
  display: inline-block;
  border: 2px solid black;
  margin: 2px;
}
@page
{
  size: auto;
  margin-left: 7mm;
  margin-right: 3mm;
  margin-top: 9mm;
  margin-bottom: 3mm;
}
@media print
{
  table { page-break-after:auto }
  tr    { page-break-inside:avoid; page-break-after:auto }
  td    { page-break-inside:avoid; page-break-after:auto }
  thead { display:table-header-group }
  tfoot { display:table-footer-group }
}
#num {
  float:right;
  display:inline-block;
}
.qrc {
  width:30px;
  height:30px;
  margin-top:1px;
}
.qrcode{
  height:80px;
  width:80px;
}
    </style>
</head>
<body onload="window.print()">

@foreach($hotspotUsers as $index => $user)
    @php
        $username = $user->username;
        $password = $user->password;
        $profile = $user->profile;
        
        // Time limit (mikhmon format - convert from seconds to string format like "1d", "12h")
        $timelimit = '';
        if ($user->limit_uptime && $user->limit_uptime > 0) {
            $seconds = $user->limit_uptime;
            $weeks = floor($seconds / 604800);
            $days = floor(($seconds % 604800) / 86400);
            $hours = floor(($seconds % 86400) / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            
            $timelimit = '';
            if ($weeks > 0) $timelimit .= $weeks . 'w';
            if ($days > 0) $timelimit .= $days . 'd';
            if ($hours > 0) $timelimit .= $hours . 'h';
            if ($minutes > 0) $timelimit .= $minutes . 'm';
            if (empty($timelimit) && $seconds > 0) $timelimit = $seconds . 's';
        }
        
        // Format bytes function (mikhmon style - must be defined once)
        if (!function_exists('formatBytes')) {
            function formatBytes($size, $decimals = 0){
                $unit = array('0' => 'Byte', '1' => 'KiB', '2' => 'MiB', '3' => 'GiB', '4' => 'TiB', '5' => 'PiB', '6' => 'EiB', '7' => 'ZiB', '8' => 'YiB');
                for($i = 0; $size >= 1024 && $i <= count($unit); $i++){
                    $size = $size/1024;
                }
                return round($size, $decimals).' '.$unit[$i];
            }
        }
        
        $getdatalimit = $user->limit_bytes_total ?? 0;
        if ($getdatalimit == 0) {
            $datalimit = "";
        } else {
            $datalimit = formatBytes($getdatalimit, 2);
        }
        
        $comment = $user->comment ?? '';
        
        // Get price from profile (mikhmon style)
        $price = '';
        $validity = '';
        $currency = \App\Models\Setting::get('currency', 'Rp');
        try {
            $service = new \App\Services\MikrotikService($router);
            $profileData = $service->getHotspotUserProfile($profile, $router);
            if ($profileData) {
                $ponlogin = $profileData['on-login'] ?? '';
                if ($ponlogin) {
                    $parts = explode(',', $ponlogin);
                    $validity = $parts[3] ?? '';
                    $getprice = $parts[2] ?? '0';
                    $getsprice = $parts[4] ?? '0';
                    
                    // Mikhmon logic: priority selling price > price
                    if($getsprice == "0" && $getprice != "0"){
                        $price = $currency . " " . number_format((float)$getprice, 0, ",", ".");
                    } elseif($getsprice != "0"){
                        $price = $currency . " " . number_format((float)$getsprice, 0, ",", ".");
                    } elseif ($getsprice == "0") {
                        $price = "";
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignore
        }
        
        $urilogin = "http://{$dnsname}/login?username={$username}&password={$password}";
        
        // Generate unique ID for QR (mikhmon style - base64 encoded)
        $uid = str_replace("=", "", base64_encode($user->id . '_' . $index));
        $num = $index + 1;
        
        // Determine user mode (vc = username = password, up = username & password)
        $usermode = ($username === $password) ? 'vc' : 'up';
        
        // QR Code HTML (mikhmon style - with script included in variable)
        $qrcode = '';
        if ($qr == 'yes') {
            $qrcode = "
	<canvas class='qrcode' id='".$uid."'></canvas>
    <script>
      (function() {
        var ".$uid." = new QRious({
          element: document.getElementById('".$uid."'),
          value: '".$urilogin."',
          size:'256'
        });
      })();
    </script>
	";
        }
        
        // Variables for template (mikhmon style - exact variable names)
        $logo = $logoUrl;
    @endphp

    @if(!empty($template))
        {{-- Use custom template from database or external URL (PHP format - mikhmon style) --}}
        @php
            // Create temporary file for template
            $tempFile = tempnam(sys_get_temp_dir(), 'voucher_template_');
            file_put_contents($tempFile, $template);
            
            // Execute PHP template with variables (mikhmon style)
            // Template sudah include QR code script di variable $qrcode jika qr == 'yes'
            ob_start();
            include $tempFile;
            $rendered = ob_get_clean();
            unlink($tempFile);
            echo $rendered;
        @endphp
    @elseif($small == 'yes')
        @include('hotspot.voucher.template-small', [
            'username' => $username,
            'password' => $password,
            'profile' => $profile,
            'timelimit' => $timelimit,
            'datalimit' => $datalimit,
            'comment' => $comment,
            'price' => $price,
            'validity' => $validity,
            'hotspotname' => $hotspotname,
            'logoUrl' => $logoUrl,
            'dnsname' => $dnsname,
            'qr' => $qr,
            'usermode' => $usermode,
            'num' => $num,
            'urilogin' => $urilogin,
            'uid' => $uid
        ])
    @else
        @include('hotspot.voucher.template', [
            'username' => $username,
            'password' => $password,
            'profile' => $profile,
            'timelimit' => $timelimit,
            'datalimit' => $datalimit,
            'comment' => $comment,
            'price' => $price,
            'validity' => $validity,
            'hotspotname' => $hotspotname,
            'logoUrl' => $logoUrl,
            'dnsname' => $dnsname,
            'qr' => $qr,
            'usermode' => $usermode,
            'num' => $num,
            'urilogin' => $urilogin,
            'uid' => $uid
        ])
    @endif

    @if($qr == 'yes')
        <script>
        (function() {
            var {{ $uid }} = new QRious({
                element: document.getElementById('{{ $uid }}'),
                value: '{{ $urilogin }}',
                size: 80
            });
        })();
        </script>
    @endif
@endforeach

</body>
</html>

