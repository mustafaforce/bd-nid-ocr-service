<?php

namespace Tests\Feature\Nid;

use App\Application\Nid\Contracts\OcrEngine;
use App\Infrastructure\Nid\Ocr\DonutOcrEngine;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ExtractNidInformationTest extends TestCase
{
    public function test_it_extracts_nid_information_from_front_and_back_images(): void
    {
        $this->app->instance(OcrEngine::class, new class implements OcrEngine
        {
            private int $calls = 0;

            public function extractText(string $imagePath, string $languages): string
            {
                $texts = [
                    <<<TEXT
                    Name: MD RAKIB HASAN
                    নাম: মোঃ রাকিব হাসান
                    Father's Name: MD ABDUL KADER
                    পিতার নাম: মোঃ আব্দুল কাদের
                    Date of Birth: 12/05/1995
                    National ID No: 19951234567890123
                    Blood Group: B+
                    TEXT,
                    <<<TEXT
                    Address: House 12, Road 3, Dhaka
                    ঠিকানা: বাড়ি ১২, সড়ক ৩, ঢাকা
                    Mother's Name: MST RAHIMA BEGUM
                    মাতার নাম: মোছাঃ রহিমা বেগম
                    Date of Issue: 01/01/2024
                    TEXT,
                ];

                return $texts[$this->calls++] ?? '';
            }
        });

        $response = $this->postJson('/api/v1/nid/extract', [
            'front_image' => UploadedFile::fake()->image('front.jpg'),
            'back_image' => UploadedFile::fake()->image('back.jpg'),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.nid_number', '19951234567890123')
            ->assertJsonPath('data.name', 'MD RAKIB HASAN')
            ->assertJsonPath('data.father_name', 'MD ABDUL KADER')
            ->assertJsonPath('data.mother_name', 'MST RAHIMA BEGUM')
            ->assertJsonPath('data.address', 'House 12, Road 3, Dhaka')
            ->assertJsonPath('data.blood_group', 'B+')
            ->assertJsonPath('data.date_of_birth', '12/05/1995')
            ->assertJsonPath('data.issue_date', '01/01/2024');
    }

    public function test_it_validates_required_images(): void
    {
        $response = $this->postJson('/api/v1/nid/extract', []);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['front_image', 'back_image']);
    }

    public function test_it_accepts_heic_and_heif_uploads(): void
    {
        $this->app->instance(OcrEngine::class, new class implements OcrEngine
        {
            public function extractText(string $imagePath, string $languages): string
            {
                return 'Name: TEST USER';
            }
        });

        $response = $this->postJson('/api/v1/nid/extract', [
            'front_image' => UploadedFile::fake()->create('front.heic', 100, 'image/heic'),
            'back_image' => UploadedFile::fake()->create('back.heif', 100, 'image/heif'),
        ]);

        $response->assertOk();
    }

    public function test_it_uses_donut_engine_when_driver_is_set_to_donut(): void
    {
        Config::set('nid.ocr.driver', 'donut');
        Config::set('nid.ocr.donut.url', 'http://127.0.0.1:8100');

        Http::fake([
            'http://127.0.0.1:8100/health' => Http::response([
                'status' => 'ok',
                'model_loaded' => true,
                'device' => 'cpu',
            ], 200),
            'http://127.0.0.1:8100/extract' => Http::response([
                'success' => true,
                'data' => [
                    'name' => 'MD RAKIB HASAN',
                    'nid_number' => '7363064945',
                    'dob' => '02/03/2000',
                    'blood_group' => 'B+',
                    'father_name' => 'KARIM UDDIN',
                    'mother_name' => 'RAHIMA BEGUM',
                    'address' => 'Sitakund, Chittagong',
                    'issue_date' => '10/05/2018',
                ],
            ], 200),
        ]);

        $this->app->bind(OcrEngine::class, fn ($app): OcrEngine => $app->make(DonutOcrEngine::class));

        $response = $this->postJson('/api/v1/nid/extract', [
            'front_image' => UploadedFile::fake()->image('front.jpg'),
            'back_image' => UploadedFile::fake()->image('back.jpg'),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'MD RAKIB HASAN')
            ->assertJsonPath('data.nid_number', '7363064945')
            ->assertJsonPath('data.date_of_birth', '02/03/2000');
    }
}
