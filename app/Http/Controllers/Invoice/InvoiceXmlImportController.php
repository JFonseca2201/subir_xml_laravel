<?php

namespace App\Http\Controllers\Invoice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class InvoiceXmlImportController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'xml' => 'required|file|mimes:xml,txt'
        ]);

        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($request->file('xml'));

        if (!$xml) {
            return response()->json([
                'message' => 'Invalid XML file'
            ], 422);
        }

        return response()->json([
            'message' => 'XML loaded successfully, ready to process'
        ]);
    }
}