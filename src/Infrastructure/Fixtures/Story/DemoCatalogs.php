<?php

declare(strict_types=1);

namespace App\Infrastructure\Fixtures\Story;

use App\Infrastructure\Fixtures\CatalogBuilder;

/**
 * Demo catalogs of the three MyOil's shops (fictional brand, decision #31). Net prices in cents,
 * chosen so the gross prices are tidy (e.g. net 41.28 → €49.95 incl. 21% VAT).
 * Variants: [sku, pack name, volume ml, weight g, net price, on hand].
 *
 * @phpstan-import-type CatalogData from CatalogBuilder
 */
final class DemoCatalogs
{
    /**
     * @return array<string, CatalogData>
     */
    public static function all(): array
    {
        return [
            'myoils-auto' => self::auto(),
            'myoils-industrie' => self::industrie(),
            'myoils-agri' => self::agri(),
        ];
    }

    /** @return CatalogData */
    private static function auto(): array
    {
        return [
            'attributes' => [
                ['code' => 'sae_viscosity', 'name' => 'SAE viscosity', 'type' => 'select', 'options' => ['0W-20', '5W-30', '5W-40', '10W-40', '20W-50', '75W-90']],
                ['code' => 'specification', 'name' => 'Specification', 'type' => 'multiselect', 'options' => ['ACEA C3', 'ACEA A3/B4', 'API SP', 'API SN', 'Dexron VI', 'API GL-5']],
                ['code' => 'approvals', 'name' => 'OEM approvals', 'type' => 'multiselect', 'options' => ['VW 504.00/507.00', 'MB 229.51', 'BMW LL-04']],
                ['code' => 'base_oil', 'name' => 'Base oil', 'type' => 'select', 'options' => ['Fully synthetic', 'Semi-synthetic', 'Mineral']],
                ['code' => 'freezing_point', 'name' => 'Freezing point', 'type' => 'number', 'unit' => '°C', 'filterable' => false],
            ],
            'categories' => [
                ['slug' => 'engine-oil', 'name' => 'Engine oil'],
                ['slug' => 'passenger-car', 'name' => 'Passenger car', 'parent' => 'engine-oil'],
                ['slug' => 'van-suv', 'name' => 'Van & SUV', 'parent' => 'engine-oil'],
                ['slug' => 'classic-cars', 'name' => 'Classic cars', 'parent' => 'engine-oil'],
                ['slug' => 'gear-atf', 'name' => 'Gear & ATF'],
                ['slug' => 'coolants', 'name' => 'Coolants'],
            ],
            'products' => [
                ['slug' => 'synth-pro-5w-30', 'name' => "MyOil's Synth Pro 5W-30", 'categories' => ['passenger-car'],
                    'description' => 'Fully synthetic low-SAPS engine oil for modern petrol and diesel engines with particulate filters. Long drain intervals, excellent cold-start protection.',
                    'specs' => ['sae_viscosity' => '5W-30', 'specification' => ['ACEA C3', 'API SN'], 'approvals' => ['VW 504.00/507.00', 'MB 229.51'], 'base_oil' => 'Fully synthetic'],
                    'variants' => [['SP530-1', '1 L', 1000, 950, 1070, 140], ['SP530-5', '5 L', 5000, 4600, 4128, 60], ['SP530-20', '20 L', 20000, 18500, 13967, 12], ['SP530-208', '208 L drum', 208000, 190000, 123058, 2]]],
                ['slug' => 'longlife-0w-20', 'name' => "MyOil's Longlife 0W-20", 'categories' => ['passenger-car'],
                    'description' => 'Fuel-economy engine oil for the latest hybrid and petrol engines.',
                    'specs' => ['sae_viscosity' => '0W-20', 'specification' => ['API SP'], 'base_oil' => 'Fully synthetic'],
                    'variants' => [['LL020-1', '1 L', 1000, 950, 1198, 80], ['LL020-5', '5 L', 5000, 4600, 4508, 25]]],
                ['slug' => 'diesel-tech-5w-30', 'name' => "MyOil's Diesel Tech 5W-30", 'categories' => ['van-suv', 'passenger-car'],
                    'description' => 'Engine oil for vans and SUVs with heavy loads and towing.',
                    'specs' => ['sae_viscosity' => '5W-30', 'specification' => ['ACEA C3'], 'approvals' => ['MB 229.51', 'BMW LL-04'], 'base_oil' => 'Fully synthetic'],
                    'variants' => [['DT530-5', '5 L', 5000, 4600, 4339, 30], ['DT530-20', '20 L', 20000, 18500, 15000, 8]]],
                ['slug' => 'eco-drive-5w-30', 'name' => "MyOil's Eco Drive 5W-30", 'categories' => ['passenger-car'],
                    'description' => 'Affordable semi-synthetic oil for everyday driving.',
                    'specs' => ['sae_viscosity' => '5W-30', 'specification' => ['ACEA C3'], 'base_oil' => 'Semi-synthetic'],
                    'variants' => [['ED530-5', '5 L', 5000, 4600, 3715, 3]]],
                ['slug' => 'power-10w-40', 'name' => "MyOil's Power 10W-40", 'categories' => ['passenger-car', 'van-suv'],
                    'description' => 'Semi-synthetic engine oil for older petrol and diesel engines.',
                    'specs' => ['sae_viscosity' => '10W-40', 'specification' => ['ACEA A3/B4'], 'base_oil' => 'Semi-synthetic'],
                    'variants' => [['PW1040-1', '1 L', 1000, 950, 740, 200], ['PW1040-5', '5 L', 5000, 4600, 2892, 90], ['PW1040-60', '60 L', 60000, 55000, 29000, 0]]],
                ['slug' => 'classic-20w-50', 'name' => "MyOil's Classic 20W-50", 'categories' => ['classic-cars'],
                    'description' => 'Mineral engine oil for classic cars and old-timers.',
                    'specs' => ['sae_viscosity' => '20W-50', 'base_oil' => 'Mineral'],
                    'variants' => [['CL2050-1', '1 L', 1000, 950, 1115, 40], ['CL2050-5', '5 L', 5000, 4600, 4050, 15]]],
                ['slug' => 'atf-multi', 'name' => "MyOil's ATF Multi", 'categories' => ['gear-atf'],
                    'description' => 'Automatic transmission fluid for most modern automatic gearboxes.',
                    'specs' => ['specification' => ['Dexron VI'], 'base_oil' => 'Fully synthetic'],
                    'variants' => [['ATFM-1', '1 L', 1000, 900, 905, 120], ['ATFM-20', '20 L', 20000, 18000, 12500, 6]]],
                ['slug' => 'gear-75w-90', 'name' => "MyOil's Gear 75W-90", 'categories' => ['gear-atf'],
                    'description' => 'Fully synthetic gear oil for manual gearboxes and differentials.',
                    'specs' => ['sae_viscosity' => '75W-90', 'specification' => ['API GL-5'], 'base_oil' => 'Fully synthetic'],
                    'variants' => [['GR7590-1', '1 L', 1000, 950, 1487, 50]]],
                ['slug' => 'coolant-g12-plus-plus', 'name' => "MyOil's Coolant G12++", 'categories' => ['coolants'],
                    'description' => 'Ready-to-use long-life coolant, protects to −37 °C.',
                    'specs' => ['freezing_point' => -37],
                    'variants' => [['G12-5', '5 L', 5000, 5300, 740, 75], ['G12-20', '20 L', 20000, 21000, 2479, 10]]],
            ],
        ];
    }

    /** @return CatalogData */
    private static function industrie(): array
    {
        return [
            'attributes' => [
                ['code' => 'iso_vg', 'name' => 'ISO VG', 'type' => 'select', 'options' => ['ISO VG 32', 'ISO VG 46', 'ISO VG 68', 'ISO VG 100', 'ISO VG 220']],
                ['code' => 'specification', 'name' => 'Specification', 'type' => 'multiselect', 'options' => ['DIN 51524-2 HLP', 'DIN 51506 VDL', 'DIN 51517-3 CLP', 'DIN 51502 CGLP']],
                ['code' => 'base_oil', 'name' => 'Base oil', 'type' => 'select', 'options' => ['Mineral', 'Synthetic']],
                ['code' => 'viscosity_40', 'name' => 'Viscosity at 40 °C', 'type' => 'number', 'unit' => 'cSt', 'filterable' => false],
            ],
            'categories' => [
                ['slug' => 'hydraulic-oils', 'name' => 'Hydraulic oils'],
                ['slug' => 'compressor-oils', 'name' => 'Compressor oils'],
                ['slug' => 'metalworking', 'name' => 'Metalworking'],
                ['slug' => 'slideway-oils', 'name' => 'Slideway oils', 'parent' => 'metalworking'],
                ['slug' => 'gear-oils', 'name' => 'Industrial gear oils'],
            ],
            'products' => [
                ['slug' => 'hydra-hlp-46', 'name' => "MyOil's Hydra HLP 46", 'categories' => ['hydraulic-oils'],
                    'description' => 'Zinc-containing hydraulic oil for industrial and mobile hydraulics.',
                    'specs' => ['iso_vg' => 'ISO VG 46', 'specification' => ['DIN 51524-2 HLP'], 'base_oil' => 'Mineral', 'viscosity_40' => 46],
                    'variants' => [['HLP46-20', '20 L', 20000, 18000, 7400, 40], ['HLP46-208', '208 L drum', 208000, 185000, 69000, 4]]],
                ['slug' => 'hydra-hlp-32', 'name' => "MyOil's Hydra HLP 32", 'categories' => ['hydraulic-oils'],
                    'description' => 'Hydraulic oil for systems in colder environments.',
                    'specs' => ['iso_vg' => 'ISO VG 32', 'specification' => ['DIN 51524-2 HLP'], 'base_oil' => 'Mineral', 'viscosity_40' => 32],
                    'variants' => [['HLP32-20', '20 L', 20000, 18000, 7200, 22]]],
                ['slug' => 'compressor-vdl-100', 'name' => "MyOil's Compressor VDL 100", 'categories' => ['compressor-oils'],
                    'description' => 'Oil for reciprocating air compressors.',
                    'specs' => ['iso_vg' => 'ISO VG 100', 'specification' => ['DIN 51506 VDL'], 'base_oil' => 'Mineral', 'viscosity_40' => 100],
                    'variants' => [['VDL100-5', '5 L', 5000, 4500, 3300, 30], ['VDL100-20', '20 L', 20000, 18000, 10900, 9]]],
                ['slug' => 'slideway-68', 'name' => "MyOil's Slideway 68", 'categories' => ['slideway-oils'],
                    'description' => 'Adhesive oil for machine-tool slideways; prevents stick-slip.',
                    'specs' => ['iso_vg' => 'ISO VG 68', 'specification' => ['DIN 51502 CGLP'], 'base_oil' => 'Mineral', 'viscosity_40' => 68],
                    'variants' => [['SW68-20', '20 L', 20000, 18000, 8600, 5]]],
                ['slug' => 'gear-clp-220', 'name' => "MyOil's Gear CLP 220", 'categories' => ['gear-oils'],
                    'description' => 'Synthetic industrial gear oil for heavily loaded gearboxes.',
                    'specs' => ['iso_vg' => 'ISO VG 220', 'specification' => ['DIN 51517-3 CLP'], 'base_oil' => 'Synthetic', 'viscosity_40' => 220],
                    'variants' => [['CLP220-20', '20 L', 20000, 18500, 18900, 7]]],
            ],
        ];
    }

    /** @return CatalogData */
    private static function agri(): array
    {
        return [
            'attributes' => [
                ['code' => 'sae_viscosity', 'name' => 'SAE viscosity', 'type' => 'select', 'options' => ['10W-30', '10W-40', '15W-40', 'SAE 30']],
                ['code' => 'specification', 'name' => 'Specification', 'type' => 'multiselect', 'options' => ['UTTO', 'STOU', 'API GL-4', 'JASO FD', 'ISO-L-EGD']],
                ['code' => 'application', 'name' => 'Application', 'type' => 'select', 'options' => ['Tractor', 'Marine 2-stroke', 'Chainsaw']],
            ],
            'categories' => [
                ['slug' => 'tractor-oils', 'name' => 'Tractor oils'],
                ['slug' => 'marine', 'name' => 'Marine'],
                ['slug' => 'forestry', 'name' => 'Forestry'],
            ],
            'products' => [
                ['slug' => 'utto-10w-30', 'name' => "MyOil's UTTO 10W-30", 'categories' => ['tractor-oils'],
                    'description' => 'Universal tractor transmission oil for gearboxes, wet brakes and hydraulics.',
                    'specs' => ['sae_viscosity' => '10W-30', 'specification' => ['UTTO', 'API GL-4'], 'application' => 'Tractor'],
                    'variants' => [['UTTO-20', '20 L', 20000, 18000, 8300, 30], ['UTTO-208', '208 L drum', 208000, 185000, 78000, 3]]],
                ['slug' => 'stou-15w-40', 'name' => "MyOil's STOU 15W-40", 'categories' => ['tractor-oils'],
                    'description' => 'Super tractor oil universal: one oil for engine, transmission and hydraulics.',
                    'specs' => ['sae_viscosity' => '15W-40', 'specification' => ['STOU'], 'application' => 'Tractor'],
                    'variants' => [['STOU-20', '20 L', 20000, 18000, 8900, 14]]],
                ['slug' => 'marine-2t', 'name' => "MyOil's Marine 2T", 'categories' => ['marine'],
                    'description' => 'Two-stroke oil for outboard engines.',
                    'specs' => ['specification' => ['JASO FD'], 'application' => 'Marine 2-stroke'],
                    'variants' => [['M2T-1', '1 L', 1000, 900, 1050, 60]]],
                ['slug' => 'chainsaw-bio', 'name' => "MyOil's Chainsaw Bio", 'categories' => ['forestry'],
                    'description' => 'Biodegradable chainsaw chain oil.',
                    'specs' => ['sae_viscosity' => 'SAE 30', 'application' => 'Chainsaw'],
                    'variants' => [['CSB-5', '5 L', 5000, 4600, 2400, 25]]],
            ],
        ];
    }
}
