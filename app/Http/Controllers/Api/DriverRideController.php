<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DriverRide;
use Illuminate\Http\Request;

class DriverRideController extends Controller
{
    public function all()
    {
        return response()->json(DriverRide::latest('ride_date')->latest()->get());
    }

    public function index($driverId)
    {
        return response()->json(DriverRide::where('driver_id', $driverId)->latest('ride_date')->latest()->get());
    }

    public function store(Request $request, $driverId)
    {
        $data = $this->validated($request);
        $data['driver_id'] = $driverId;
        if ($request->hasFile('paymentProof')) $data['payment_proof'] = $request->file('paymentProof')->store('driver-payment-proofs', 'public');
        unset($data['paymentProof']);
        $data['total_amount'] = (float) $data['rideAmount'] + (float) $data['otherCharges'];
        return response()->json(['ride' => DriverRide::create($this->map($data))], 201);
    }

    public function update(Request $request, $driverId, DriverRide $ride)
    {
        abort_unless((int) $ride->driver_id === (int) $driverId, 404);
        $data = $this->validated($request);
        if ($request->hasFile('paymentProof')) $data['payment_proof'] = $request->file('paymentProof')->store('driver-payment-proofs', 'public');
        unset($data['paymentProof']);
        $data['total_amount'] = (float) $data['rideAmount'] + (float) $data['otherCharges'];
        $ride->update($this->map($data));
        return response()->json(['ride' => $ride->fresh()]);
    }

    public function destroy($driverId, DriverRide $ride)
    {
        abort_unless((string) $ride->driver_id === (string) $driverId, 404);
        $ride->delete();
        return response()->json(['message' => 'Ride deleted.']);
    }

    public function destroyAny(DriverRide $ride)
    {
        $ride->delete();
        return response()->json(['message' => 'Ride deleted.']);
    }

    private function validated(Request $request)
    {
        return $request->validate(['rideDate' => ['required', 'date'], 'customerName' => ['required', 'string', 'max:255'], 'customerPhone' => ['nullable', 'string', 'max:50'], 'pickup' => ['required', 'string', 'max:255'], 'destination' => ['required', 'string', 'max:255'], 'tripType' => ['required', 'string', 'max:80'], 'distanceKm' => ['nullable', 'numeric', 'min:0'], 'rideAmount' => ['required', 'numeric', 'min:0'], 'driverPayment' => ['required', 'numeric', 'min:0'], 'otherCharges' => ['required', 'numeric', 'min:0'], 'paymentStatus' => ['required', 'in:paid,unpaid,partial'], 'paymentMethod' => ['nullable', 'string', 'max:80'], 'notes' => ['nullable', 'string'], 'paymentProof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240']]);
    }

    private function map(array $data)
    {
        return ['driver_id' => $data['driver_id'] ?? null, 'ride_date' => $data['rideDate'], 'customer_name' => $data['customerName'], 'customer_phone' => $data['customerPhone'] ?? null, 'pickup' => $data['pickup'], 'destination' => $data['destination'], 'trip_type' => $data['tripType'], 'distance_km' => $data['distanceKm'] ?? null, 'ride_amount' => $data['rideAmount'], 'driver_payment' => $data['driverPayment'], 'other_charges' => $data['otherCharges'], 'total_amount' => $data['total_amount'], 'payment_status' => $data['paymentStatus'], 'payment_method' => $data['paymentMethod'] ?? null, 'notes' => $data['notes'] ?? null, 'payment_proof' => $data['payment_proof'] ?? null];
    }
}
