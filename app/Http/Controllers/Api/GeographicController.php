<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GeographicController extends Controller
{
    private function getUbigeoData()
    {
        if (!Storage::disk('local')->exists('ubigeo.json')) {
            return [];
        }
        
        $json = Storage::disk('local')->get('ubigeo.json');
        return json_decode($json, true) ?? [];
    }

    public function getRegions()
    {
        $data = $this->getUbigeoData();
        
        $regions = array_map(function($region) {
            return [
                'id' => $region['id'],
                'name' => $region['name']
            ];
        }, $data);

        return response()->json($regions);
    }

    public function getProvinces($regionId)
    {
        $data = $this->getUbigeoData();
        
        $provinces = [];
        foreach ($data as $region) {
            if ($region['id'] === $regionId) {
                if (isset($region['provinces'])) {
                    $provinces = array_map(function($province) {
                        return [
                            'id' => $province['id'],
                            'name' => $province['name']
                        ];
                    }, $region['provinces']);
                }
                break;
            }
        }

        return response()->json($provinces);
    }

    public function getCities($provinceId)
    {
        $data = $this->getUbigeoData();
        
        $districts = [];
        foreach ($data as $region) {
            if (isset($region['provinces'])) {
                foreach ($region['provinces'] as $province) {
                    if ($province['id'] === $provinceId) {
                        if (isset($province['districts'])) {
                            $districts = array_map(function($district) {
                                return [
                                    'id' => $district['id'],
                                    'name' => $district['name']
                                ];
                            }, $province['districts']);
                        }
                        break 2;
                    }
                }
            }
        }

        return response()->json($districts);
    }
}
