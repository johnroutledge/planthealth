<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Welcome to Plant AI</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-light text-dark">
    <div class="container mt-5">
        <h1 class="text-center">Welcome to Plant AI</h1>
        <p class="text-center">Upload an image of a plant for analysis</p>

        <div class="row justify-content-center">
            <div class="col-md-6">
                <form action="{{ route('check-plant-health') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label for="image" class="form-label">Choose an image</label>
                        <input type="file" class="form-control" id="image" name="image" @if(isset($uploadedFileName)) readonly @endif>
                        
                        @if(isset($uploadedFileName))
                            <input type="text" class="form-control mt-2" value="{{ $uploadedFileName }}" readonly>
                        @endif
                    </div>
                
                    <div class="mb-3">
                        <label class="form-label">Select Analysis Type:</label>
                        <div>
                            <input type="radio" id="plant-identification" name="analysis_type" value="identification"
                                {{ $analysisType === 'identification' ? 'checked' : '' }}>
                            <label for="plant-identification">Plant Identification</label>
                        </div>
                        <div>
                            <input type="radio" id="health-check" name="analysis_type" value="health"
                                {{ $analysisType === 'health' ? 'checked' : '' }}>
                            <label for="health-check">Health Check</label>
                        </div>
                    </div>
                
                    <button type="submit" class="btn btn-primary">Analyze</button>
                
                    <!-- Image Preview Section -->
                    @if(isset($imagePreviewUrl))
                        <div class="mt-3">
                            <label for="image-preview" class="form-label">Your chosen image:</label>
                            <img id="image-preview" src="{{ $imagePreviewUrl }}" alt="Image Preview" style="max-width: 100%;">
                        </div>
                    @endif
                </form>
                
            </div>
        </div>

        @if (isset($result))
            
            @if ($analysisType == 'identification')
            <div class="mt-5">
                {{-- Local PlantId JSON file --}}
                {{-- <h4>Possible Identifications (using locally stored PlantId JSON file):</h4>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Plant Name</th>
                            <th>Probability (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($result['suggestions'] as $suggestion)
                            <tr>
                                <td>{{ $suggestion['plant_name'] }}</td>
                                <td>{{ number_format($suggestion['probability'] * 100, 2) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table> --}}

                {{-- PlantNet API --}}
                {{-- <h4>Possible Identifications (using PantNet API):</h4>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Scientific Name</th>
                            <th>Common Names</th>
                            <th>Score (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($result['results'] as $result)
                            <tr>
                                <td>{{ $result['species']['scientificName'] }}</td>
                                <td>
                                    @if(isset($result['species']['commonNames']) && count($result['species']['commonNames']) > 0)
                                        {{ implode(', ', $result['species']['commonNames']) }}
                                    @else
                                        <i>No common names available</i>
                                    @endif
                                </td>
                                <td>{{ number_format($result['score'] * 100, 2) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table> --}}

                {{-- PlantID API --}}
                @if($result['result']['is_plant'] && $result['result']['is_plant']['probability'] > 0.5)
                    <p>The plant is likely identified!</p>
                @else
                    <p>The plant could not be identified.</p>
                @endif

                @if(isset($result['result']['classification']['suggestions']) && count($result['result']['classification']['suggestions']) > 0)
                    <h4>Possible Identifications (using PantId API):</h4>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Plant Name</th>
                                <th>Probability (%)</th>
                                <th>Similar Images</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($result['result']['classification']['suggestions'] as $suggestion)
                                <tr>
                                    <td>{{ $suggestion['name'] }}</td>
                                    <td>{{ number_format($suggestion['probability'] * 100, 2) }}%</td>
                                    <td>
                                        @foreach($suggestion['similar_images'] as $image)
                                            <img src="{{ $image['url_small'] }}" alt="{{ $suggestion['name'] }}" style="width: 100px; height: auto; margin-right: 5px;">
                                        @endforeach
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

            </div>
            @else
                @if(isset($result))
                    <div class="mt-5">
                        <h3>Analysis Results</h3>
                        @if($result['result']['is_healthy']['binary'])
                            <p>This plant is healthy.</p>
                        @else
                            @if($result['result']['is_healthy']['probability'] < $result['result']['is_healthy']['threshold'])
                                <p>This plant is unhealthy.</p>
                            @else
                                <p>This plant is at risk, but may be healthy with further care.</p>
                            @endif
                        @endif
                        
                        <h4>Possible Issues:</h4>
                        <ul>
                            @foreach(array_slice($result['result']['disease']['suggestions'], 0, 3) as $suggestion)
                                <li>
                                    <strong>{{ $suggestion['name'] }}</strong> 
                                    (Probability:  {{ number_format($suggestion['probability'] * 100, 2) }}%)
                                    
                                    <p>{{ $suggestion['details']['description'] ?? 'No description available' }}</p>
                                    
                                    <h5>Treatments:</h5>
                                    <ul>
                                        <li><strong>Chemical:</strong>
                                            <ul>
                                                @foreach($suggestion['details']['treatment']['chemical'] ?? [] as $chemical)
                                                    <li>{{ $chemical }}</li>
                                                @endforeach
                                            </ul>
                                        </li>
                        
                                        <li><strong>Biological:</strong>
                                            <ul>
                                                @foreach($suggestion['details']['treatment']['biological'] ?? [] as $biological)
                                                    <li>{{ $biological }}</li>
                                                @endforeach
                                            </ul>
                                        </li>
                        
                                        <li><strong>Prevention:</strong>
                                            <ul>
                                                @foreach($suggestion['details']['treatment']['prevention'] ?? [] as $prevention)
                                                    <li>{{ $prevention }}</li>
                                                @endforeach
                                            </ul>
                                        </li>
                                    </ul>
                        
                                    <h5>Similar Images:</h5>
                                    <ul>
                                        @foreach($suggestion['similar_images'] as $image)
                                            <li>
                                                <img src="{{ $image['url'] }}" alt="{{ $suggestion['name'] }}" width="100">
                                            </li>
                                        @endforeach
                                    </ul>                        
                                </li>
                            @endforeach
                        </ul>
                        
                        
                    
                    </div>
                @endif
            @endif
                
        @endif

    </div>
</body>
</html>
