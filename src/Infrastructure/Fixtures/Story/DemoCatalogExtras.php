<?php

declare(strict_types=1);

namespace App\Infrastructure\Fixtures\Story;

use App\Infrastructure\Fixtures\CatalogBuilder;

/**
 * Phase 8 demo range on top of DemoCatalogs (which the automated tests use unchanged): about 15
 * products per shop in 3-level category trees. Variants: [sku, pack name, volume ml, weight g,
 * net price in cents, on hand].
 *
 * @phpstan-import-type CatalogData from CatalogBuilder
 */
final class DemoCatalogExtras
{
    /**
     * The test catalogs plus the extra range, merged per shop.
     *
     * @return array<string, CatalogData>
     */
    public static function demo(): array
    {
        $extras = ['myoils-auto' => self::auto(), 'myoils-industrie' => self::industrie(), 'myoils-agri' => self::agri()];
        $catalogs = [];
        foreach (DemoCatalogs::all() as $code => $catalog) {
            $catalogs[$code] = self::merge($catalog, $extras[$code]);
        }

        return $catalogs;
    }

    /**
     * @param CatalogData                                                                                                                                                                                                                                                                           $base
     * @param array{options: array<string, list<string>>, attributes?: list<array{code: string, name: string, type: string, options?: list<string>, unit?: string, filterable?: bool}>, categories: list<array{slug: string, name: string, parent?: string}>, products: list<array<string, mixed>>} $extra
     *
     * @return CatalogData
     */
    private static function merge(array $base, array $extra): array
    {
        foreach ($base['attributes'] as $i => $attribute) {
            if (isset($extra['options'][$attribute['code']])) {
                $base['attributes'][$i]['options'] = array_values(array_unique([...$attribute['options'] ?? [], ...$extra['options'][$attribute['code']]]));
            }
        }
        $base['attributes'] = [...$base['attributes'], ...$extra['attributes'] ?? []];
        $base['categories'] = [...$base['categories'], ...$extra['categories']];
        $base['products'] = [...$base['products'], ...$extra['products']];

        return $base;
    }

    /**
     * @return array{options: array<string, list<string>>, categories: list<array{slug: string, name: string, parent?: string}>, products: list<array<string, mixed>>}
     */
    private static function auto(): array
    {
        return [
            'options' => ['specification' => ['DOT 4', 'DOT 5.1']],
            'categories' => [
                ['slug' => 'brake-fluids', 'name' => 'Brake fluids'],
                ['slug' => 'brake-clutch', 'name' => 'Brake & clutch', 'parent' => 'brake-fluids'],
                ['slug' => 'dot-4', 'name' => 'DOT 4', 'parent' => 'brake-clutch'],
                ['slug' => 'dot-5-1', 'name' => 'DOT 5.1', 'parent' => 'brake-clutch'],
                ['slug' => 'care', 'name' => 'Care products'],
                ['slug' => 'additives', 'name' => 'Additives', 'parent' => 'care'],
                ['slug' => 'fuel-system', 'name' => 'Fuel system', 'parent' => 'additives'],
                ['slug' => 'engine-care', 'name' => 'Engine care', 'parent' => 'additives'],
                ['slug' => 'car-care', 'name' => 'Car care', 'parent' => 'care'],
            ],
            'products' => [
                ['slug' => 'brake-fluid-dot-4', 'name' => "MyOil's Brake Fluid DOT 4", 'categories' => ['dot-4'],
                    'description' => 'Glycol-based brake fluid for hydraulic brake and clutch systems. Change every two years.',
                    'specs' => ['specification' => ['DOT 4']],
                    'variants' => [['BF4-05', '0.5 L', 500, 560, 454, 90], ['BF4-1', '1 L', 1000, 1100, 783, 45]]],
                ['slug' => 'brake-fluid-dot-5-1', 'name' => "MyOil's Brake Fluid DOT 5.1", 'categories' => ['dot-5-1'],
                    'description' => 'High-boiling brake fluid for cars with ABS and ESP and for sporty driving.',
                    'specs' => ['specification' => ['DOT 5.1']],
                    'variants' => [['BF51-05', '0.5 L', 500, 560, 619, 40]]],
                ['slug' => 'injector-cleaner', 'name' => "MyOil's Injector Cleaner", 'categories' => ['fuel-system'],
                    'description' => 'Cleans petrol injectors and valves; one bottle treats up to 60 litres of fuel.',
                    'specs' => [],
                    'variants' => [['INJ-300', '300 ml', 300, 340, 825, 120]]],
                ['slug' => 'diesel-anti-gel', 'name' => "MyOil's Diesel Anti-Gel", 'categories' => ['fuel-system'],
                    'description' => 'Keeps diesel flowing down to -30 °C. Add before the first frost.',
                    'specs' => ['freezing_point' => -30],
                    'variants' => [['DAG-250', '250 ml', 250, 290, 660, 8]]],
                ['slug' => 'engine-flush', 'name' => "MyOil's Engine Flush", 'categories' => ['engine-care'],
                    'description' => 'Loosens sludge before an oil change. Add to the old oil and idle for ten minutes.',
                    'specs' => [],
                    'variants' => [['EFL-400', '400 ml', 400, 450, 1032, 55]]],
                ['slug' => 'screenwash-winter', 'name' => "MyOil's Screenwash Winter", 'categories' => ['car-care'],
                    'description' => 'Ready-to-use winter screenwash, frost-proof to -20 °C.',
                    'specs' => ['freezing_point' => -20],
                    'variants' => [['SCW-5', '5 L', 5000, 5200, 578, 150], ['SCW-20', '20 L', 20000, 20800, 1983, 0]]],
            ],
        ];
    }

    /**
     * @return array{options: array<string, list<string>>, categories: list<array{slug: string, name: string, parent?: string}>, products: list<array<string, mixed>>}
     */
    private static function industrie(): array
    {
        return [
            'options' => [
                'iso_vg' => ['ISO VG 10', 'ISO VG 22', 'ISO VG 150', 'ISO VG 320'],
                'specification' => ['DIN 51524-3 HVLP', 'ISO 15380 HEES', 'DIN 51825 KP2K-30', 'DIN 51502 CGLP'],
                'base_oil' => ['Synthetic ester'],
            ],
            'categories' => [
                ['slug' => 'bio-hydraulic', 'name' => 'Biodegradable hydraulic oils', 'parent' => 'hydraulic-oils'],
                ['slug' => 'hvlp-hydraulic', 'name' => 'HVLP hydraulic oils', 'parent' => 'hydraulic-oils'],
                ['slug' => 'screw-compressors', 'name' => 'Screw compressors', 'parent' => 'compressor-oils'],
                ['slug' => 'cutting-fluids', 'name' => 'Cutting fluids', 'parent' => 'metalworking'],
                ['slug' => 'water-miscible', 'name' => 'Water-miscible', 'parent' => 'cutting-fluids'],
                ['slug' => 'neat-cutting', 'name' => 'Neat cutting oils', 'parent' => 'cutting-fluids'],
                ['slug' => 'spindle-oils', 'name' => 'Spindle oils', 'parent' => 'metalworking'],
                ['slug' => 'greases', 'name' => 'Greases'],
                ['slug' => 'lithium-greases', 'name' => 'Lithium greases', 'parent' => 'greases'],
                ['slug' => 'ep-greases', 'name' => 'EP greases', 'parent' => 'lithium-greases'],
            ],
            'products' => [
                ['slug' => 'hydra-bio-hees-46', 'name' => "MyOil's Hydra Bio HEES 46", 'categories' => ['bio-hydraulic'],
                    'description' => 'Readily biodegradable synthetic ester hydraulic fluid for machines near water and in forestry.',
                    'specs' => ['iso_vg' => 'ISO VG 46', 'specification' => ['ISO 15380 HEES'], 'base_oil' => 'Synthetic ester', 'viscosity_40' => 46],
                    'variants' => [['HEES46-20', '20 L', 20000, 18400, 16500, 12], ['HEES46-208', '208 L drum', 208000, 190000, 158000, 1]]],
                ['slug' => 'hydra-hvlp-46', 'name' => "MyOil's Hydra HVLP 46", 'categories' => ['hvlp-hydraulic'],
                    'description' => 'High viscosity index hydraulic oil for outdoor machines with changing temperatures.',
                    'specs' => ['iso_vg' => 'ISO VG 46', 'specification' => ['DIN 51524-3 HVLP'], 'base_oil' => 'Mineral', 'viscosity_40' => 46],
                    'variants' => [['HVLP46-20', '20 L', 20000, 18000, 8900, 35], ['HVLP46-60', '60 L', 60000, 54000, 25600, 6], ['HVLP46-208', '208 L drum', 208000, 185000, 83500, 3]]],
                ['slug' => 'compressor-screw-46', 'name' => "MyOil's Compressor Screw 46", 'categories' => ['screw-compressors'],
                    'description' => 'Synthetic oil for rotary screw compressors; up to 8,000 operating hours.',
                    'specs' => ['iso_vg' => 'ISO VG 46', 'base_oil' => 'Synthetic', 'viscosity_40' => 46],
                    'variants' => [['CS46-5', '5 L', 5000, 4500, 5750, 18], ['CS46-20', '20 L', 20000, 18000, 19900, 6]]],
                ['slug' => 'cut-emulsion', 'name' => "MyOil's Cut Emulsion", 'categories' => ['water-miscible'],
                    'description' => 'Mineral-oil based emulsion concentrate for turning and milling, 4-6 % in water.',
                    'specs' => ['base_oil' => 'Mineral'],
                    'variants' => [['CUTE-20', '20 L', 20000, 19000, 9800, 20], ['CUTE-208', '208 L drum', 208000, 198000, 92000, 2]]],
                ['slug' => 'cut-neat-22', 'name' => "MyOil's Cut Neat 22", 'categories' => ['neat-cutting'],
                    'description' => 'Neat cutting oil for threading, broaching and gear hobbing.',
                    'specs' => ['iso_vg' => 'ISO VG 22', 'base_oil' => 'Mineral', 'viscosity_40' => 22],
                    'variants' => [['CUTN22-20', '20 L', 20000, 17600, 10400, 9]]],
                ['slug' => 'spindle-10', 'name' => "MyOil's Spindle 10", 'categories' => ['spindle-oils'],
                    'description' => 'Thin oil for high-speed spindle bearings.',
                    'specs' => ['iso_vg' => 'ISO VG 10', 'base_oil' => 'Mineral', 'viscosity_40' => 10],
                    'variants' => [['SP10-5', '5 L', 5000, 4300, 2700, 14]]],
                ['slug' => 'gear-clp-320', 'name' => "MyOil's Gear CLP 320", 'categories' => ['gear-oils'],
                    'description' => 'Industrial gear oil for slow-running, heavily loaded gearboxes.',
                    'specs' => ['iso_vg' => 'ISO VG 320', 'specification' => ['DIN 51517-3 CLP'], 'base_oil' => 'Mineral', 'viscosity_40' => 320],
                    'variants' => [['CLP320-20', '20 L', 20000, 18500, 11200, 10], ['CLP320-208', '208 L drum', 208000, 190000, 104000, 2]]],
                ['slug' => 'gear-clp-150', 'name' => "MyOil's Gear CLP 150", 'categories' => ['gear-oils'],
                    'description' => 'Industrial gear oil for worm and spur gears.',
                    'specs' => ['iso_vg' => 'ISO VG 150', 'specification' => ['DIN 51517-3 CLP'], 'base_oil' => 'Mineral', 'viscosity_40' => 150],
                    'variants' => [['CLP150-20', '20 L', 20000, 18400, 10100, 0]]],
                ['slug' => 'grease-ep2', 'name' => "MyOil's Grease EP2", 'categories' => ['ep-greases'],
                    'description' => 'Lithium EP grease for bearings, joints and slides. Cartridge fits standard grease guns.',
                    'specs' => ['specification' => ['DIN 51825 KP2K-30']],
                    'variants' => [['EP2-400', '400 g cartridge', 400, 450, 494, 300], ['EP2-18KG', '18 kg pail', 18000, 19000, 11900, 7]]],
                ['slug' => 'grease-ep0', 'name' => "MyOil's Grease EP0", 'categories' => ['ep-greases'],
                    'description' => 'Semi-fluid lithium grease for enclosed gearboxes and central lubrication systems.',
                    'specs' => [],
                    'variants' => [['EP0-18KG', '18 kg pail', 18000, 19000, 12800, 4]]],
            ],
        ];
    }

    /**
     * @return array{options: array<string, list<string>>, categories: list<array{slug: string, name: string, parent?: string}>, products: list<array<string, mixed>>}
     */
    private static function agri(): array
    {
        return [
            'options' => [
                'sae_viscosity' => ['80W-90'],
                'specification' => ['API GL-5', 'NMMA TC-W3', 'ACEA E7'],
                'application' => ['Marine 4-stroke', 'Forestry 2-stroke'],
            ],
            'categories' => [
                ['slug' => 'tractor-engine', 'name' => 'Engine oils', 'parent' => 'tractor-oils'],
                ['slug' => 'tractor-transmission', 'name' => 'Transmission & hydraulics', 'parent' => 'tractor-oils'],
                ['slug' => 'tractor-gear', 'name' => 'Axle & gear oils', 'parent' => 'tractor-transmission'],
                ['slug' => 'inboard', 'name' => 'Inboard engines', 'parent' => 'marine'],
                ['slug' => 'outboard', 'name' => 'Outboard engines', 'parent' => 'marine'],
                ['slug' => 'outboard-gear', 'name' => 'Outboard gear oils', 'parent' => 'outboard'],
                ['slug' => 'chain-oils', 'name' => 'Chain oils', 'parent' => 'forestry'],
                ['slug' => 'two-stroke', 'name' => 'Two-stroke', 'parent' => 'forestry'],
            ],
            'products' => [
                ['slug' => 'tractor-shpd-15w-40', 'name' => "MyOil's Tractor SHPD 15W-40", 'categories' => ['tractor-engine'],
                    'description' => 'Super high performance diesel oil for tractors and harvesters.',
                    'specs' => ['sae_viscosity' => '15W-40', 'specification' => ['ACEA E7'], 'application' => 'Tractor'],
                    'variants' => [['SHPD-20', '20 L', 20000, 18000, 8100, 24], ['SHPD-208', '208 L drum', 208000, 185000, 76000, 2]]],
                ['slug' => 'tractor-lowsaps-10w-40', 'name' => "MyOil's Tractor Low-SAPS 10W-40", 'categories' => ['tractor-engine'],
                    'description' => 'Low-SAPS engine oil for Stage V tractors with particulate filter.',
                    'specs' => ['sae_viscosity' => '10W-40', 'specification' => ['ACEA E7'], 'application' => 'Tractor'],
                    'variants' => [['LS1040-20', '20 L', 20000, 18000, 9900, 11]]],
                ['slug' => 'axle-gear-80w-90', 'name' => "MyOil's Axle Gear 80W-90", 'categories' => ['tractor-gear'],
                    'description' => 'EP gear oil for axles and final drives.',
                    'specs' => ['sae_viscosity' => '80W-90', 'specification' => ['API GL-5'], 'application' => 'Tractor'],
                    'variants' => [['AG8090-20', '20 L', 20000, 18000, 8700, 16]]],
                ['slug' => 'loader-hydraulic-46', 'name' => "MyOil's Loader Hydraulic 46", 'categories' => ['tractor-transmission'],
                    'description' => 'Hydraulic oil for front loaders, tippers and log splitters.',
                    'specs' => ['application' => 'Tractor'],
                    'variants' => [['LH46-20', '20 L', 20000, 18000, 6900, 30], ['LH46-60', '60 L', 60000, 54000, 19800, 5]]],
                ['slug' => 'marine-4t-10w-40', 'name' => "MyOil's Marine 4T 10W-40", 'categories' => ['inboard'],
                    'description' => 'Four-stroke oil for inboard and outboard engines, protects against salt-water corrosion.',
                    'specs' => ['sae_viscosity' => '10W-40', 'application' => 'Marine 4-stroke'],
                    'variants' => [['M4T-1', '1 L', 1000, 900, 1230, 45], ['M4T-5', '5 L', 5000, 4500, 5350, 20]]],
                ['slug' => 'marine-tcw3', 'name' => "MyOil's Marine TC-W3", 'categories' => ['outboard'],
                    'description' => 'NMMA TC-W3 two-stroke oil for water-cooled outboards.',
                    'specs' => ['specification' => ['NMMA TC-W3'], 'application' => 'Marine 2-stroke'],
                    'variants' => [['TCW3-1', '1 L', 1000, 900, 1150, 60], ['TCW3-5', '5 L', 5000, 4500, 4990, 7]]],
                ['slug' => 'outboard-gear-80w-90', 'name' => "MyOil's Outboard Gear 80W-90", 'categories' => ['outboard-gear'],
                    'description' => 'Lower unit gear oil for outboard engines and sterndrives.',
                    'specs' => ['sae_viscosity' => '80W-90', 'specification' => ['API GL-5'], 'application' => 'Marine 2-stroke'],
                    'variants' => [['OBG-1', '1 L', 1000, 950, 1320, 25]]],
                ['slug' => 'marine-grease', 'name' => "MyOil's Marine Grease", 'categories' => ['marine'],
                    'description' => 'Water-resistant grease for trailers, winches and steering.',
                    'specs' => [],
                    'variants' => [['MGR-400', '400 g cartridge', 400, 450, 620, 70]]],
                ['slug' => 'forest-2t-mix', 'name' => "MyOil's Forest 2T Mix Oil", 'categories' => ['two-stroke'],
                    'description' => 'Low-smoke two-stroke oil for chainsaws and brush cutters, 1:50.',
                    'specs' => ['specification' => ['JASO FD', 'ISO-L-EGD'], 'application' => 'Forestry 2-stroke'],
                    'variants' => [['F2T-1', '1 L', 1000, 900, 990, 80]]],
                ['slug' => 'alkylate-2t', 'name' => "MyOil's Alkylate 2T Ready Mix", 'categories' => ['two-stroke'],
                    'description' => 'Ready-mixed alkylate petrol for two-stroke tools: cleaner exhaust, long storage.',
                    'specs' => ['application' => 'Forestry 2-stroke'],
                    'variants' => [['ALK2T-5', '5 L', 5000, 3900, 1900, 0]]],
                ['slug' => 'chain-oil-mineral', 'name' => "MyOil's Chain Oil Mineral", 'categories' => ['chain-oils'],
                    'description' => 'Adhesive chain oil for chainsaws; stays on the bar at high speed.',
                    'specs' => ['sae_viscosity' => 'SAE 30', 'application' => 'Chainsaw'],
                    'variants' => [['CHM-5', '5 L', 5000, 4600, 1650, 40]]],
            ],
        ];
    }
}
