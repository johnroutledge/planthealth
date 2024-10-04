<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PlantHealthController extends Controller
{
    public function analyzeImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image',
        ]);

        $image = $request->file('image');
        $uploadedFileName = $image->getClientOriginalName();
        $imageTempPath = $image->getRealPath();
        $imagePreviewUrl = 'data:'.$image->getClientMimeType().';base64,'.base64_encode(file_get_contents($imageTempPath));

        // Uncomment the following code block to use local JSON files for testing
        if (app()->environment('local')) {
            Log::info('Request: '.json_encode($request->all()));

            if ($request['analysis_type'] == 'identification') {
                $responseJson = Storage::disk('local')->get('plant-id-identification.json');
                $analysisType = 'identification';
            } else {
                $responseJson = Storage::disk('local')->get('with-treatment.json');
                $analysisType = 'health';
            }
            $responseArray = json_decode($responseJson, true);

            return view('welcome', ['result' => $responseArray, 'analysisType' => $analysisType]);
        }

        $request->validate([
            'image' => 'required|image',
        ]);

        $image = $request->file('image');
        $uploadedFileName = $image->getClientOriginalName();
        $imageTempPath = $image->getRealPath();
        $imagePreviewUrl = 'data:'.$image->getClientMimeType().';base64,'.base64_encode(file_get_contents($imageTempPath));

        if ($request['analysis_type'] == 'identification') {
            $analysisType = 'identification';
            $response = $this->identifyPlant($image);
        } else {
            $analysisType = 'health';
            $response = $this->analyzePlantHealth($image);
        }

        if ($response->successful()) {
            return view('welcome', [
                'result' => $response->json(),
                'analysisType' => $analysisType,
                'imagePreviewUrl' => $imagePreviewUrl,
                'uploadedFileName' => $uploadedFileName,
            ]);
        } else {
            return view('welcome', [
                'error' => 'Failed to analyze image: '.$response->body(),
                'analysisType' => $analysisType,
            ]);
        }
    }

    private function identifyPlant($image)
    {
        Log::info('Identifying plant...');
        /**
         * PlantNet API
         * UNCOMMENT THE FOLLOWING CODE BLOCK TO USE PLANTNET API
         */
        // $plantNetApiKey = env('PLANT_NET_API_KEY');
        // $plantNetApiUrl = 'https://my-api.plantnet.org/v2/identify/all';

        // $queryParams = [
        //     'include-related-images' => 'false',
        //     'no-reject' => 'false',
        //     'nb-results' => 10,
        //     'lang' => 'en',
        //     'api-key' => $plantNetApiKey,
        // ];

        // $response = Http::asMultipart()
        //     ->attach(
        //         'images', file_get_contents($image->getRealPath()), $image->getClientOriginalName() // Attach image
        //     )
        //     ->attach(
        //         'organs', 'flower' // Attach organ type, e.g., leaf
        //     )
        //     ->post($plantNetApiUrl.'?'.http_build_query($queryParams));
        // Log::info('PlantNet API response: '.json_encode($response->json()));

        /**
         * PlantID API
         * UNCOMMENT THE FOLLOWING CODE BLOCK TO USE PLANTID API
         */
        $latitude = 49.207; // Replace with dynamic value if needed
        $longitude = 16.608; // Replace with dynamic value if needed

        $response = Http::withHeaders([
            'Api-Key' => env('PLANT_ID_API_KEY'),
        ])->attach(
            'images[]', file_get_contents($image->getRealPath()), $image->getClientOriginalName()
        )->post('https://plant.id/api/v3/identification', [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'similar_images' => 'true',
        ]);

        Log::info('PlantID API response: '.json_encode($response->json()));

        return $response;
    }

    public function analyzePlantHealth($image)
    {
        Log::info('Analyzing plant health...');
        $latitude = 49.207; // Replace with dynamic value if needed
        $longitude = 16.608; // Replace with dynamic value if needed

        $imagePath = $image->getRealPath();
        $imageData = file_get_contents($imagePath);
        $imageBase64 = base64_encode($imageData);
        $imageType = $image->getClientMimeType(); // Get the MIME type of the uploaded image

        // Create the request payload
        $data = [
            'images' => ["data:{$imageType};base64,{$imageBase64}"],
            'latitude' => $latitude,
            'longitude' => $longitude,
            'similar_images' => true,
            'health' => 'only',
        ];

        // Make the request to the health assessment endpoint
        $response = Http::withHeaders([
            'Api-Key' => env('PLANT_ID_API_KEY'), // API key from environment
            'Content-Type' => 'application/json', // Ensure we're sending JSON
        ])->post('https://plant.id/api/v3/health_assessment?language=en&details=local_name,description,url,treatment,classification,common_names,cause', $data);

        Log::info('Plant health analysis response: '.json_encode($response->json()));

        return $response;

    }

    public function returnTestData()
    {
        $responseJson = Storage::disk('local')->get('with-treatment.json');

        $data = json_decode($responseJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json(['error' => 'Invalid JSON format'], 500);
        }

        return response()->json($data);
    }
}
