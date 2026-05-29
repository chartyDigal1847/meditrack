<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ClinicVisit;
use App\Models\Diagnosis;
use App\Models\EmergencyAlert;
use App\Models\HealthReport;
use App\Models\MedicalRecord;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = (string) $request->validate(['q' => 'nullable|string|max:120'])['q'];

        return response()->json([
            'success' => true,
            'data' => [
                'clinic_visits' => ClinicVisit::with('student')->where('chief_complaint', 'like', "%{$q}%")->limit(8)->get(),
                'medical_records' => MedicalRecord::with('student')->where('title', 'like', "%{$q}%")->orWhere('summary', 'like', "%{$q}%")->limit(8)->get(),
                'diagnoses' => Diagnosis::with('student')->where('title', 'like', "%{$q}%")->limit(8)->get(),
                'emergency_alerts' => EmergencyAlert::with('student')->where('title', 'like', "%{$q}%")->limit(8)->get(),
                'health_reports' => HealthReport::where('title', 'like', "%{$q}%")->limit(8)->get(),
            ],
        ]);
    }
}
