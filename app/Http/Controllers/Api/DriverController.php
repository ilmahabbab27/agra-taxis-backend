<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DriverController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'fullName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:drivers,email'],
            'phone' => ['required', 'string', 'max:50'],
            'username' => ['sometimes', 'alpha_dash', 'max:80', 'unique:drivers,username'],
            'password' => ['required', 'string', 'min:8'],
            'province' => ['required', 'string', 'max:100'],
            'district' => ['required', 'string', 'max:100'],
            'location' => ['required', 'string', 'max:255'],
            'vehicleCategory' => ['required', 'string', 'max:100'],
            'vehicleName' => ['required', 'string', 'max:150'],
            'vehicleRegistrationNumber' => ['required', 'string', 'max:50'],
            'vehicleColour' => ['required', 'string', 'max:80'],
            'seatCapacity' => ['required', 'integer', 'min:1', 'max:100'],
            'airConditioning' => ['required', 'in:ac,non_ac'],
            'vehiclePhotos' => ['required', 'array', 'min:1', 'max:5'],
            'vehiclePhotos.*' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,avif', 'max:5120'],
            'driverDocument' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'insuranceDocument' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ]);

        $vehiclePhotos = array_map(fn ($file) => $file->store('driver-documents', 'public'), $request->file('vehiclePhotos', []));
        $driverDocument = $request->file('driverDocument')->store('driver-documents', 'public');
        $insuranceDocument = $request->file('insuranceDocument')->store('driver-documents', 'public');

        $username = $data['username'] ?? Str::slug(Str::before($data['email'], '@'));
        $baseUsername = $username ?: 'driver';
        $suffix = 1;
        while (Driver::where('username', $username)->exists()) {
            $username = $baseUsername . $suffix++;
        }

        $driver = Driver::create([
            'full_name' => $data['fullName'], 'email' => $data['email'], 'phone' => $data['phone'],
            'username' => $username, 'password' => Hash::make($data['password']),
            'province' => $data['province'], 'district' => $data['district'], 'location' => $data['location'],
            'vehicle_category' => $data['vehicleCategory'], 'vehicle_name' => $data['vehicleName'],
            'vehicle_registration_number' => $data['vehicleRegistrationNumber'], 'vehicle_colour' => $data['vehicleColour'],
            'seat_capacity' => $data['seatCapacity'], 'air_conditioning' => $data['airConditioning'],
            'vehicle_photos' => $vehiclePhotos, 'driver_document' => $driverDocument,
            'insurance_document' => $insuranceDocument,
        ]);

        return response()->json(['message' => 'Driver application received.', 'driver' => $driver], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $driver = Driver::where('email', $data['email'])->first();
        if (! $driver) return response()->json(['message' => 'This email is not registered as a driver.'], 422);
        if (! Hash::check($data['password'], $driver->password)) return response()->json(['message' => 'Incorrect password.'], 422);
        return response()->json(['token' => $driver->createToken('driver')->plainTextToken, 'driver' => $driver]);
    }

    public function account(Request $request)
    {
        return response()->json(['driver' => $request->user()]);
    }

    public function updateAccount(Request $request)
    {
        $driver = $request->user();
        $data = $request->validate([
            'fullName' => ['sometimes', 'required', 'string', 'max:255'], 'email' => ['sometimes', 'required', 'email', 'max:255', 'unique:drivers,email,' . $driver->id],
            'phone' => ['sometimes', 'required', 'string', 'max:50'], 'province' => ['sometimes', 'required', 'string', 'max:100'],
            'district' => ['sometimes', 'required', 'string', 'max:100'], 'location' => ['sometimes', 'required', 'string', 'max:255'],
            'vehicleCategory' => ['sometimes', 'required', 'string', 'max:100'], 'vehicleName' => ['sometimes', 'required', 'string', 'max:150'],
            'vehicleRegistrationNumber' => ['sometimes', 'required', 'string', 'max:50'], 'vehicleColour' => ['sometimes', 'required', 'string', 'max:80'],
            'seatCapacity' => ['sometimes', 'required', 'integer', 'min:1', 'max:100'], 'airConditioning' => ['sometimes', 'required', 'in:ac,non_ac'],
            'password' => ['sometimes', 'required', 'string', 'min:8'],
            'vehiclePhotos' => ['sometimes', 'array', 'max:5'], 'vehiclePhotos.*' => ['file', 'mimes:jpg,jpeg,png,gif,webp,avif', 'max:5120'],
            'driverDocument' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'insuranceDocument' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ]);
        $updates = [
            'full_name' => $data['fullName'] ?? null, 'email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null,
            'province' => $data['province'] ?? null, 'district' => $data['district'] ?? null, 'location' => $data['location'] ?? null,
            'vehicle_category' => $data['vehicleCategory'] ?? null, 'vehicle_name' => $data['vehicleName'] ?? null,
            'vehicle_registration_number' => $data['vehicleRegistrationNumber'] ?? null, 'vehicle_colour' => $data['vehicleColour'] ?? null,
            'seat_capacity' => $data['seatCapacity'] ?? null, 'air_conditioning' => $data['airConditioning'] ?? null,
        ];
        if (! empty($data['password'])) $updates['password'] = Hash::make($data['password']);
        if ($request->hasFile('vehiclePhotos')) $updates['vehicle_photos'] = array_map(fn ($file) => $file->store('driver-documents', 'public'), $request->file('vehiclePhotos'));
        if ($request->hasFile('driverDocument')) $updates['driver_document'] = $request->file('driverDocument')->store('driver-documents', 'public');
        if ($request->hasFile('insuranceDocument')) $updates['insurance_document'] = $request->file('insuranceDocument')->store('driver-documents', 'public');
        $driver->update(array_filter($updates, fn ($value) => $value !== null));
        return response()->json(['message' => 'Account updated.', 'driver' => $driver->fresh()]);
    }

    public function destroy(Driver $driver)
    {
        $driver->delete();
        return response()->json(['message' => 'Driver deleted.']);
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $driver = Driver::where('email', $data['email'])->first();
        if ($driver) {
            $token = Str::random(64);
            DB::table('password_resets')->updateOrInsert(['email' => $driver->email], ['token' => Hash::make($token), 'created_at' => now()]);
            try {
                Mail::raw("Use this token to change your Agra Taxis driver password: {$token}", function ($message) use ($driver) {
                    $message->to($driver->email)->subject('Agra Taxis driver password reset');
                });
            } catch (\Throwable $exception) {
                Log::error('Driver password reset email failed', ['email' => $driver->email, 'error' => $exception->getMessage()]);
                return response()->json(['message' => 'Email could not be sent. Check the SMTP settings.'], 503);
            }
        }
        return response()->json(['message' => 'If the email exists, a reset token has been sent.']);
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate(['token' => ['required', 'string'], 'password' => ['required', 'string', 'min:8']]);
        $reset = DB::table('password_resets')->get()->first(fn ($row) => Hash::check($data['token'], $row->token));
        if (! $reset || now()->diffInHours($reset->created_at) > 1) return response()->json(['message' => 'This reset token is invalid or expired.'], 422);
        Driver::where('email', $reset->email)->update(['password' => Hash::make($data['password'])]);
        DB::table('password_resets')->where('email', $reset->email)->delete();
        return response()->json(['message' => 'Password changed successfully.']);
    }

    public function index()
    {
        return response()->json(Driver::latest()->get());
    }

    public function updateStatus(Request $request, Driver $driver)
    {
        $data = $request->validate([
            'fullName' => ['sometimes', 'required', 'string', 'max:255'], 'email' => ['sometimes', 'required', 'email', 'max:255', 'unique:drivers,email,' . $driver->id],
            'phone' => ['sometimes', 'required', 'string', 'max:50'], 'province' => ['sometimes', 'required', 'string', 'max:100'],
            'district' => ['sometimes', 'required', 'string', 'max:100'], 'location' => ['sometimes', 'required', 'string', 'max:255'],
            'vehicleCategory' => ['sometimes', 'required', 'string', 'max:100'], 'vehicleName' => ['sometimes', 'required', 'string', 'max:150'],
            'vehicleRegistrationNumber' => ['sometimes', 'required', 'string', 'max:50'], 'vehicleColour' => ['sometimes', 'required', 'string', 'max:80'],
            'seatCapacity' => ['sometimes', 'required', 'integer', 'min:1', 'max:100'], 'airConditioning' => ['sometimes', 'required', 'in:ac,non_ac'],
            'status' => ['sometimes', 'required', 'in:pending,approved,rejected'],
            'password' => ['sometimes', 'required', 'string', 'min:8'],
            'vehiclePhotos' => ['sometimes', 'array', 'max:5'], 'vehiclePhotos.*' => ['file', 'mimes:jpg,jpeg,png,gif,webp,avif', 'max:5120'],
            'driverDocument' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'insuranceDocument' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ]);
        $updates = [
            'full_name' => $data['fullName'] ?? null, 'email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null,
            'province' => $data['province'] ?? null, 'district' => $data['district'] ?? null, 'location' => $data['location'] ?? null,
            'vehicle_category' => $data['vehicleCategory'] ?? null, 'vehicle_name' => $data['vehicleName'] ?? null,
            'vehicle_registration_number' => $data['vehicleRegistrationNumber'] ?? null, 'vehicle_colour' => $data['vehicleColour'] ?? null,
            'seat_capacity' => $data['seatCapacity'] ?? null, 'air_conditioning' => $data['airConditioning'] ?? null, 'status' => $data['status'] ?? null,
        ];
        if (! empty($data['password'])) $updates['password'] = Hash::make($data['password']);
        if ($request->hasFile('vehiclePhotos')) $updates['vehicle_photos'] = array_map(fn ($file) => $file->store('driver-documents', 'public'), $request->file('vehiclePhotos'));
        if ($request->hasFile('driverDocument')) $updates['driver_document'] = $request->file('driverDocument')->store('driver-documents', 'public');
        if ($request->hasFile('insuranceDocument')) $updates['insurance_document'] = $request->file('insuranceDocument')->store('driver-documents', 'public');
        $driver->update(array_filter([
            'full_name' => $data['fullName'] ?? null, 'email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null,
            'province' => $data['province'] ?? null, 'district' => $data['district'] ?? null, 'location' => $data['location'] ?? null,
            'vehicle_category' => $data['vehicleCategory'] ?? null, 'vehicle_name' => $data['vehicleName'] ?? null,
            'vehicle_registration_number' => $data['vehicleRegistrationNumber'] ?? null, 'vehicle_colour' => $data['vehicleColour'] ?? null,
            'seat_capacity' => $data['seatCapacity'] ?? null, 'air_conditioning' => $data['airConditioning'] ?? null, 'status' => $data['status'] ?? null,
        ], fn ($value) => $value !== null));
        $driver->update(array_filter($updates, fn ($value) => $value !== null));
        return response()->json(['message' => 'Driver status updated.', 'driver' => $driver]);
    }
}
