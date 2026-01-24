<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Task;

/**
 * Service for overdue task display utilities.
 */
final class OverdueService
{
    // Severity thresholds (matching Task entity)
    public const SEVERITY_LOW = Task::OVERDUE_SEVERITY_LOW;      // 'low' - 1-2 days
    public const SEVERITY_MEDIUM = Task::OVERDUE_SEVERITY_MEDIUM; // 'medium' - 3-7 days
    public const SEVERITY_HIGH = Task::OVERDUE_SEVERITY_HIGH;    // 'high' - 7+ days

    // Tailwind CSS color classes for severity levels
    private const SEVERITY_COLORS = [
        self::SEVERITY_LOW => 'text-yellow-600 bg-yellow-50 border-yellow-200',
        self::SEVERITY_MEDIUM => 'text-orange-600 bg-orange-50 border-orange-200',
        self::SEVERITY_HIGH => 'text-red-600 bg-red-50 border-red-200',
    ];

    // Border color classes for task cards
    private const SEVERITY_BORDER_COLORS = [
        self::SEVERITY_LOW => 'border-l-yellow-500',
        self::SEVERITY_MEDIUM => 'border-l-orange-500',
        self::SEVERITY_HIGH => 'border-l-red-500',
    ];

    // Human-readable labels
    private const SEVERITY_LABELS = [
        self::SEVERITY_LOW => '1-2 days overdue',
        self::SEVERITY_MEDIUM => '3-7 days overdue',
        self::SEVERITY_HIGH => 'More than a week overdue',
    ];

    /**
     * Get the Tailwind CSS color classes for a severity level.
     */
    public function getSeverityColorClass(string $severity): string
    {
        return self::SEVERITY_COLORS[$severity] ?? '';
    }

    /**
     * Get the border color class for task card styling.
     */
    public function getSeverityBorderClass(string $severity): string
    {
        return self::SEVERITY_BORDER_COLORS[$severity] ?? '';
    }

    /**
     * Get the human-readable label for a severity level.
     */
    public function getSeverityLabel(string $severity): string
    {
        return self::SEVERITY_LABELS[$severity] ?? '';
    }

    /**
     * Get display info for a task's overdue state.
     *
     * @return array{
     *     isOverdue: bool,
     *     days: int|null,
     *     severity: string|null,
     *     colorClass: string,
     *     borderClass: string,
     *     label: string
     * }
     */
    public function getOverdueDisplayInfo(Task $task): array
    {
        $isOverdue = $task->isOverdue();
        $days = $task->getOverdueDays();
        $severity = $task->getOverdueSeverity();

        return [
            'isOverdue' => $isOverdue,
            'days' => $days,
            'severity' => $severity,
            'colorClass' => $severity ? $this->getSeverityColorClass($severity) : '',
            'borderClass' => $severity ? $this->getSeverityBorderClass($severity) : '',
            'label' => $severity ? $this->getSeverityLabel($severity) : '',
        ];
    }

    /**
     * Get all severity options for filter dropdowns.
     *
     * @return array<string, array{value: string, label: string, colorClass: string}>
     */
    public function getSeverityOptions(): array
    {
        return [
            self::SEVERITY_LOW => [
                'value' => self::SEVERITY_LOW,
                'label' => self::SEVERITY_LABELS[self::SEVERITY_LOW],
                'colorClass' => self::SEVERITY_COLORS[self::SEVERITY_LOW],
            ],
            self::SEVERITY_MEDIUM => [
                'value' => self::SEVERITY_MEDIUM,
                'label' => self::SEVERITY_LABELS[self::SEVERITY_MEDIUM],
                'colorClass' => self::SEVERITY_COLORS[self::SEVERITY_MEDIUM],
            ],
            self::SEVERITY_HIGH => [
                'value' => self::SEVERITY_HIGH,
                'label' => self::SEVERITY_LABELS[self::SEVERITY_HIGH],
                'colorClass' => self::SEVERITY_COLORS[self::SEVERITY_HIGH],
            ],
        ];
    }
}
