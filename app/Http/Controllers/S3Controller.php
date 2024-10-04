<?php

namespace App\Http\Controllers;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class S3Controller extends Controller
{
    public function generatePresignedUrl(Request $request)
    {
        $fileName = 'photos/'.uniqid('plant_', true).'.jpg';

        // Create the S3 client
        $s3Client = new S3Client([
            'version' => 'latest',
            'region' => env('AWS_DEFAULT_REGION'),
            'credentials' => [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ],
        ]);

        try {
            // Create the S3 PutObject command
            $cmd = $s3Client->getCommand('PutObject', [
                'Bucket' => env('AWS_BUCKET'),
                'Key' => $fileName,
                'ContentType' => 'image/jpeg',
                'ACL' => 'public-read',
            ]);

            // Generate a presigned request valid for 20 minutes
            $presignedRequest = $s3Client->createPresignedRequest($cmd, '+20 minutes');
            $presignedUrl = (string) $presignedRequest->getUri();

            // Extract the headers for the presigned request (if any)
            $headers = [];
            foreach ($presignedRequest->getHeaders() as $key => $value) {
                $headers[$key] = implode(', ', $value); // Convert header array to a string
            }

            // Respond with the presigned URL and headers in the required format
            return response()->json([
                'data' => [
                    'presigned_url' => $presignedUrl,
                    'headers' => $headers, // Include the headers for the client to use
                ],
            ]);
        } catch (AwsException $e) {
            // Log the error and return a response with an error message
            Log::error('AWS S3 Error: '.$e->getMessage());

            return response()->json(['error' => 'Could not generate presigned URL.'], 500);
        }
    }

    public function confirmUpload(Request $request)
    {
        $request->validate([
            'presigned_url' => 'required|string',
        ]);

        $presignedUrl = $request->input('presigned_url');

        Log::info('Incoming presigned URL: '.$presignedUrl);

        $parsedUrl = parse_url($presignedUrl);

        if (isset($parsedUrl['path'])) {
            $fileName = ltrim($parsedUrl['path'], '/'); // Remove leading slash
            Log::info('Extracted full file path from URL: '.$fileName);
        } else {
            return response()->json(['error' => 'Invalid presigned URL or missing file name'], 400);
        }

        $fileName = trim($fileName);

        if (empty($fileName)) {
            return response()->json(['error' => 'Invalid presigned URL or missing file name'], 400);
        }

        if (! Storage::disk('s3')->exists($fileName)) {
            Log::error('File not found on S3: '.$fileName);

            return response()->json(['error' => 'File not found on S3'], 404);
        }

        $image = Storage::disk('s3')->get($fileName);

        $tempFile = tmpfile();
        fwrite($tempFile, $image);
        $tempFilePath = stream_get_meta_data($tempFile)['uri'];

        $response = $this->analyzePlantHealth(new \Illuminate\Http\UploadedFile($tempFilePath, basename($fileName)));

        fclose($tempFile);

        return response()->json($response);
    }

    public function analyzePlantHealth($image)
    {
        Log::info('Analyzing plant health...');
        $latitude = 49.207; // Replace with dynamic value if needed
        $longitude = 16.608; // Replace with dynamic value if needed

        $imagePath = $image->getRealPath();
        $imageData = file_get_contents($imagePath);
        $imageBase64 = base64_encode($imageData);
        $imageType = $image->getClientMimeType();

        $data = [
            'images' => ["data:{$imageType};base64,{$imageBase64}"],
            'latitude' => $latitude,
            'longitude' => $longitude,
            'similar_images' => true,
            'health' => 'only',
        ];

        $response = Http::withHeaders([
            'Api-Key' => env('PLANT_ID_API_KEY'),
            'Content-Type' => 'application/json',
        ])->post('https://plant.id/api/v3/health_assessment?language=en&details=local_name,description,url,treatment,classification,common_names,cause', $data);

        Log::info($response->json()['result']);

        return response()->json(['result' => $response->json()['result']]);
    }

    public function testFileExists()
    {
        // Hard-coded S3 file name
        $fileName = 'photos/plant_66fff040c71334.19459886.jpg';

        // Check if the file exists on S3
        if (! Storage::disk('s3')->exists($fileName)) {
            Log::error('File not found on S3: '.$fileName);

            return response()->json(['error' => 'File not found on S3'], 404);
        }

        // If the file exists, you can continue processing
        $image = Storage::disk('s3')->get($fileName);

        // Process the image or whatever logic you want to perform
        return response()->json(['message' => 'File exists and has been retrieved successfully']);
    }

    public function testFile()
    {
        $fileName = 'photos/plant_66fff040c71334.19459886.jpg';

        $image = Storage::disk('s3')->get($fileName);

        $tempFile = tmpfile();
        fwrite($tempFile, $image);
        $tempFilePath = stream_get_meta_data($tempFile)['uri'];

        $response = $this->analyzePlantHealth(new \Illuminate\Http\UploadedFile($tempFilePath, basename($fileName)));

        fclose($tempFile);

        return response()->json($response);
    }
}
