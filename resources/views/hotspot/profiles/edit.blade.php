@extends('layouts.admin')

@section('title', 'Edit User Profile - ' . $router->name)

@section('content')
@include('hotspot.partials.router-selector')

<div class="container-fluid">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3>
                        <i class="fa fa-edit"></i> Edit User Profile
                        @if(isset($profile['monitor_color']))
                            <i class="fa fa-circle {{ $profile['monitor_color'] }}"></i>
                        @endif
                    </h3>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('hotspot.profiles.update', $profile['id']) }}" autocomplete="off">
                        @csrf
                        @method('PUT')
                        
                        <div class="mb-3">
                            <a href="{{ route('hotspot.profiles') }}" class="btn btn-warning">
                                <i class="fa fa-close"></i> Close
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-save"></i> Save
                            </button>
                        </div>

                        <table class="table">
                            <tr>
                                <td class="align-middle">Name</td>
                                <td>
                                    <input class="form-control" type="text" name="name" value="{{ old('name', $profile['name']) }}" required autofocus onchange="remSpace(this)">
                                </td>
                            </tr>
                            <tr>
                                <td class="align-middle">Address Pool</td>
                                <td>
                                    <select class="form-control" name="address_pool">
                                        <option value="none" {{ ($profile['address_pool'] ?? 'none') == 'none' ? 'selected' : '' }}>none</option>
                                        @foreach($pools as $pool)
                                            <option value="{{ $pool['name'] }}" {{ ($profile['address_pool'] ?? 'none') == $pool['name'] ? 'selected' : '' }}>
                                                {{ $pool['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td class="align-middle">Shared Users</td>
                                <td>
                                    <input class="form-control" type="number" name="shared_users" value="{{ old('shared_users', $profile['shared_users'] ?? 1) }}" min="1" required>
                                </td>
                            </tr>
                            <tr>
                                <td class="align-middle">Rate limit [up/down]</td>
                                <td>
                                    <input class="form-control" type="text" name="rate_limit" value="{{ old('rate_limit', $profile['rate_limit'] ?? '') }}" placeholder="Example: 512k/1M">
                                </td>
                            </tr>
                            <tr>
                                <td class="align-middle">Expired Mode</td>
                                <td>
                                    <select class="form-control" name="expired_mode" id="expmode" onchange="toggleValidity()" required>
                                        <option value="0" {{ ($profile['expired_mode'] ?? '0') == '0' ? 'selected' : '' }}>None</option>
                                        <option value="rem" {{ ($profile['expired_mode'] ?? '') == 'rem' ? 'selected' : '' }}>Remove</option>
                                        <option value="ntf" {{ ($profile['expired_mode'] ?? '') == 'ntf' ? 'selected' : '' }}>Notice</option>
                                        <option value="remc" {{ ($profile['expired_mode'] ?? '') == 'remc' ? 'selected' : '' }}>Remove & Record</option>
                                        <option value="ntfc" {{ ($profile['expired_mode'] ?? '') == 'ntfc' ? 'selected' : '' }}>Notice & Record</option>
                                    </select>
                                </td>
                            </tr>
                            <tr id="validity-row" style="{{ ($profile['expired_mode'] ?? '0') == '0' ? 'display:none;' : '' }}">
                                <td class="align-middle">Validity</td>
                                <td>
                                    <input class="form-control" type="text" name="validity" id="validity" value="{{ old('validity', $profile['validity'] ?? '') }}" placeholder="Example: 8h, 30d, 7d">
                                </td>
                            </tr>
                            <tr>
                                <td class="align-middle">Price Rp</td>
                                <td>
                                    <input class="form-control" type="number" name="price" value="{{ old('price', $profile['price'] ?? '0') }}" min="0" step="0.01">
                                </td>
                            </tr>
                            <tr>
                                <td class="align-middle">Selling Price Rp</td>
                                <td>
                                    <input class="form-control" type="number" name="selling_price" value="{{ old('selling_price', $profile['selling_price'] ?? '0') }}" min="0" step="0.01">
                                </td>
                            </tr>
                            <tr>
                                <td class="align-middle">Lock User</td>
                                <td>
                                    <select class="form-control" name="lock_user" required>
                                        <option value="Disable" {{ ($profile['lock_user'] ?? 'Disable') == 'Disable' ? 'selected' : '' }}>Disable</option>
                                        <option value="Enable" {{ ($profile['lock_user'] ?? 'Disable') == 'Enable' ? 'selected' : '' }}>Enable</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td class="align-middle">Parent Queue</td>
                                <td>
                                    <select class="form-control" name="parent_queue">
                                        <option value="none" {{ ($profile['parent_queue'] ?? 'none') == 'none' ? 'selected' : '' }}>none</option>
                                        @foreach($queues as $queue)
                                            <option value="{{ $queue['name'] }}" {{ ($profile['parent_queue'] ?? 'none') == $queue['name'] ? 'selected' : '' }}>
                                                {{ $queue['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fa fa-book"></i> Read Me</h3>
                </div>
                <div class="card-body">
                    <table class="table">
                        <tr>
                            <td colspan="2">
                                <p style="padding:0px 5px;">
                                    <strong>Expired Mode:</strong><br>
                                    Expired Mode adalah kontrol untuk hotspot user.<br>
                                    Options: Remove, Notice, Remove & Record, Notice & Record.<br><br>
                                    • <strong>Remove:</strong> User akan dihapus ketika expired.<br>
                                    • <strong>Notice:</strong> User tidak akan dihapus dan mendapat notifikasi setelah expired.<br>
                                    • <strong>Record:</strong> Menyimpan harga setiap user login. Untuk menghitung total penjualan hotspot users.
                                </p>
                                <p style="padding:0px 5px;">
                                    <strong>Lock User:</strong><br>
                                    Username hanya bisa digunakan pada 1 device saja.
                                </p>
                                <p style="padding:0px 5px;">
                                    <strong>Format Validity:</strong><br>
                                    [wdhm] Example: 30d = 30days, 12h = 12hours, 30m = 30minutes<br>
                                    5hours 30minutes = 5h30m
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function remSpace(input) {
    var newValue = input.value.replace(/\s/g, "-");
    input.value = newValue;
    input.focus();
}

function toggleValidity() {
    var expMode = document.getElementById('expmode').value;
    var validityRow = document.getElementById('validity-row');
    var validityInput = document.getElementById('validity');
    
    if (expMode && expMode !== '0') {
        validityRow.style.display = '';
        validityInput.required = true;
    } else {
        validityRow.style.display = 'none';
        validityInput.required = false;
    }
}
</script>
@endsection

