<!DOCTYPE html>
<html>
<head>
    <title>Template Preview</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta http-equiv="pragma" content="no-cache" />
    <script src="{{ asset('js/qrious.min.js') }}"></script>
    <style>
body {
  color: #000000;
  background-color: #FFFFFF;
  font-size: 14px;
  font-family: 'Helvetica', arial, sans-serif;
  margin: 20px;
  -webkit-print-color-adjust: exact;
}
table.voucher {
  display: inline-block;
  border: 2px solid black;
  margin: 2px;
}
@page {
  size: auto;
  margin-left: 7mm;
  margin-right: 3mm;
  margin-top: 9mm;
  margin-bottom: 3mm;
}
@media print {
  table { page-break-after:auto }
  tr    { page-break-inside:avoid; page-break-after:auto }
  td    { page-break-inside:avoid; page-break-after:auto }
  thead { display:table-header-group }
  tfoot { display:table-footer-group }
}
.qrcode{
  height:80px;
  width:80px;
}
#num {
  float:right;
  display:inline-block;
}
.preview-container {
  max-width: 800px;
  margin: 0 auto;
  padding: 20px;
}
.preview-title {
  text-align: center;
  font-weight: bold;
  font-size: 18px;
  margin-bottom: 20px;
}
    </style>
</head>
<body>
    <div class="preview-container">
        <div class="preview-title">Template Preview</div>
        
        @php
            // Prepare template content with all variables available
            // This mimics what happens in print.blade.php
            // All variables ($logo, $hotspotname, $username, etc.) are already available
            // from the controller and passed to the view
            
            // Create temporary file for template (same as print.blade.php)
            $tempFile = tempnam(sys_get_temp_dir(), 'voucher_template_preview_');
            file_put_contents($tempFile, $template->html_content);
            
            // Execute PHP template with variables (mikhmon style)
            // Template sudah include QR code script di variable $qrcode jika qr == 'yes'
            ob_start();
            include $tempFile;
            $rendered = ob_get_clean();
            unlink($tempFile);
            echo $rendered;
        @endphp
        
        <div style="margin-top: 20px; padding: 10px; background: #f0f0f0; font-size: 12px; color: #666;">
            <strong>Note:</strong> This is a preview with sample data. PHP variables are replaced with sample values.
        </div>
    </div>
</body>
</html>

