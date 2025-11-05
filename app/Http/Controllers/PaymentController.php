<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function create()
    {
        return inertia('Payment/Create'); // Basic response for now
    }
}