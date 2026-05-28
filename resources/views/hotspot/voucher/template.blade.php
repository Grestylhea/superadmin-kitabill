<style>
	.qrcode{
		height:80px;
		width:80px;
	}
</style>

<table class="voucher" style=" width: 220px;">
  <tbody>
<!-- Logo Hotspotname -->
    <tr>
      <td style="text-align: left; font-size: 14px; font-weight:bold; border-bottom: 1px black solid;">
        <img src="{{ $logoUrl }}" alt="logo" style="height:30px;border:0;">  
        {{ $hotspotname }}  
        <span id="num">[{{ $num }}]</span>
      </td>
    </tr>
<!-- /  -->
    <tr>
      <td>
    <table style=" text-align: center; width: 210px; font-size: 12px;">
  <tbody>
<!-- Username Password QR    -->
    <tr>
      <td>
        <table style="width:100%;">
<!-- Username = Password    -->
@if($usermode == "vc")
        <tr>
          <td font-size: 12px;>Kode Voucher</td>
        </tr>
        <tr>
          <td style="width:100%; border: 1px solid black; font-weight:bold; font-size:16px;">{{ $username }}</td>
        </tr>
<!-- /  -->
<!-- Username & Password  -->
@elseif($usermode == "up")
<!-- Check QR  -->
@if($qr == "yes")
        <tr>
          <td>Username</td>
        </tr>
        <tr>
          <td style="border: 1px solid black; font-weight:bold;">{{ $username }}</td>
        </tr>
        <tr>
          <td>Password</td>
        </tr>
        <tr>
          <td style="border: 1px solid black; font-weight:bold;">{{ $password }}</td>
        </tr>
@else
        <tr>
          <td style="width: 50%">Username</td>
          <td >Password</td>
        </tr>
        <tr style="font-size: 14px;">
          <td style="border: 1px solid black; font-weight:bold;">{{ $username }}</td>
          <td style="border: 1px solid black; font-weight:bold;">{{ $password }}</td>
        </tr>
@endif
@endif
<!-- /  -->
        </table>
      </td>
<!-- QR Code    -->
@if($qr == "yes")
      <td>
        <canvas class='qrcode' id='{{ $uid }}'></canvas>
      </td>
@endif
<!-- /  -->
    <tr>
      <!-- Price  -->
      <td colspan="2" style="border-top: 1px solid black;font-weight:bold; font-size:16px">
        {{ $validity }} {{ $timelimit }} {{ $datalimit }} {{ $price }}
      </td>
<!-- /  -->
    </tr>
    <tr>
      <!-- Note  -->
      <td colspan="2" style="font-weight:bold; font-size:12px">Login: http://{{ $dnsname }}</td>
<!-- /  -->
    </tr>
<!-- /  -->
  </tbody>
    </table>
      </td>
    </tr>
  </tbody>
</table>



