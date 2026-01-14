<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Impression Étiquettes - {{ $product->item_designation }}</title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            margin: 0;
            padding: 20px;
            background-color: #f0f0f0;
        }
        .controls {
            background: white;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .label-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            background: white;
            padding: 20px;
            border-radius: 8px;
        }
        .label-item {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
            page-break-inside: avoid;
        }
        .label-name {
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 5px;
            text-transform: uppercase;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .label-img {
            max-width: 100%;
            height: auto;
            margin-bottom: 5px;
        }
        .label-code {
            font-size: 14px;
            letter-spacing: 2px;
            font-weight: bold;
        }
        @media print {
            body { 
                background: white; 
                padding: 0;
            }
            .controls { display: none; }
            .label-grid { 
                padding: 0;
                grid-template-columns: repeat(4, 1fr);
            }
            .label-item {
                border: 0.5px solid #eee;
            }
        }
    </style>
</head>
<body>
    <div class="controls">
        <div>
            <strong>Produit:</strong> {{ $product->item_designation }} | 
            <strong>Quantité:</strong> {{ $count }} |
            <strong>Type:</strong> {{ strtoupper($type) }}
        </div>
        <button onclick="window.print()" style="padding: 8px 20px; cursor: pointer; background: #007bff; color: white; border: none; border-radius: 4px; font-weight: bold;">
            Imprimer maintenant
        </button>
    </div>

    <div class="label-grid">
        @for ($i = 0; $i < $count; $i++)
            <div class="label-item">
                <div class="label-name">{{ $product->item_designation }}</div>
                @if($type === 'barcode')
                    <img src="{{ route('api.products.barcode', [$product->id, 'content' => $product->barcode ?? $product->item_code]) }}" class="label-img">
                @else
                    <img src="{{ route('api.products.qrcode', [$product->id, 'content' => $product->barcode ?? $product->item_code]) }}" class="label-img" style="width: 100px;">
                @endif
                <div class="label-code">{{ $product->barcode ?? $product->item_code }}</div>
            </div>
        @endfor
    </div>
</body>
</html>
