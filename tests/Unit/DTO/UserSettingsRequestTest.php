<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\UserSettingsRequest;
use PHPUnit\Framework\TestCase;

class UserSettingsRequestTest extends TestCase
{
    // ========================================
    // fromArray Tests
    // ========================================

    public function testFromArrayParsesDefaultSortPreset(): void
    {
        $dto = UserSettingsRequest::fromArray(['defaultSortPreset' => 'priority_due_date']);

        $this->assertEquals('priority_due_date', $dto->defaultSortPreset);
    }

    public function testFromArrayReturnsNullWhenDefaultSortPresetNotProvided(): void
    {
        $dto = UserSettingsRequest::fromArray([]);

        $this->assertNull($dto->defaultSortPreset);
    }

    // ========================================
    // toSettingsArray Tests
    // ========================================

    public function testToSettingsArrayIncludesDefaultSortPreset(): void
    {
        $dto = UserSettingsRequest::fromArray(['defaultSortPreset' => 'title_az']);
        $settings = $dto->toSettingsArray();

        $this->assertArrayHasKey('default_sort_preset', $settings);
        $this->assertEquals('title_az', $settings['default_sort_preset']);
    }

    public function testToSettingsArrayExcludesNullDefaultSortPreset(): void
    {
        $dto = UserSettingsRequest::fromArray([]);
        $settings = $dto->toSettingsArray();

        $this->assertArrayNotHasKey('default_sort_preset', $settings);
    }

    public function testToSettingsArrayIncludesAllProvidedSettings(): void
    {
        $dto = UserSettingsRequest::fromArray([
            'timezone' => 'America/New_York',
            'defaultSortPreset' => 'created_newest',
        ]);
        $settings = $dto->toSettingsArray();

        $this->assertEquals('America/New_York', $settings['timezone']);
        $this->assertEquals('created_newest', $settings['default_sort_preset']);
    }

    // ========================================
    // Valid Sort Presets Constant Tests
    // ========================================

    public function testValidSortPresetsContainsExpectedValues(): void
    {
        $this->assertContains('due_date_priority', UserSettingsRequest::VALID_SORT_PRESETS);
        $this->assertContains('priority_due_date', UserSettingsRequest::VALID_SORT_PRESETS);
        $this->assertContains('created_newest', UserSettingsRequest::VALID_SORT_PRESETS);
        $this->assertContains('created_oldest', UserSettingsRequest::VALID_SORT_PRESETS);
        $this->assertContains('updated_recent', UserSettingsRequest::VALID_SORT_PRESETS);
        $this->assertContains('title_az', UserSettingsRequest::VALID_SORT_PRESETS);
        $this->assertContains('position', UserSettingsRequest::VALID_SORT_PRESETS);
    }
}
