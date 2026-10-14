<?php

namespace Tests\Unit\System;

use App\Services\System\IranLocations;
use Tests\TestCase;

final class IranLocationsTest extends TestCase
{
    public function test_local_catalog_covers_the_pinned_source_and_preserves_city_identity(): void
    {
        $locations = new IranLocations;
        $provinces = $locations->provinces();
        $cities = $locations->cities();
        $manifest = json_decode(file_get_contents(resource_path('data/iran/source-manifest.json')), true, flags: JSON_THROW_ON_ERROR);
        $counts = array_column($manifest['generatedDatasets'], 'rowCount', 'name');

        $this->assertCount($counts['provinces'], $provinces);
        $this->assertCount($counts['cities-filtered'], $cities);
        $form = $locations->forForm();
        $formIds = [];
        foreach ($form as $province) {
            $this->assertNotEmpty($province['cities']);
            $this->assertSame($provinces[$province['id']]['name'], $province['name']);
            foreach ($province['cities'] as $city) {
                $this->assertSame((int) $province['id'], $cities[$city['id']]['province_id']);
                $formIds[] = $city['id'];
            }
            $this->assertCount(count($province['cities']), array_unique(array_column($province['cities'], 'label')));
        }
        $this->assertCount(count($cities), array_unique($formIds));
        $this->assertEqualsCanonicalizing(array_map('strval', array_keys($cities)), $formIds);
    }

    public function test_name_matching_normalizes_persian_letters_and_rejects_wrong_parent(): void
    {
        $locations = new IranLocations;
        $this->assertSame('آذربایجان شرقی', $locations->provinceByName(' آذربايجان شرقي ')['name']);
        $this->assertSame('ارومیه', $locations->cityByName('آذربایجان غربی', 'اروميه')['name']);
        $this->assertNull($locations->cityByName('تهران', 'شیراز'));
        $this->assertNull($locations->province(['100']));
        $this->assertNull($locations->city('not-an-id'));
    }

    public function test_vendored_files_match_the_recorded_source_hashes(): void
    {
        $provenance = json_decode(file_get_contents(resource_path('data/iran/provenance.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($provenance['files'] as $file => $hash) {
            $this->assertSame($hash, hash_file('sha256', resource_path('data/iran/'.$file)), $file);
        }
    }
}
