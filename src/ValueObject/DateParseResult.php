<?php

declare(strict_types=1);

namespace App\ValueObject;

final readonly class DateParseResult
{
    private function __construct(
        public ?\DateTimeImmutable $date,
        public ?string $time,
        public string $originalText,
        public int $startPosition,
        public int $endPosition,
        public bool $hasTime,
    ) {
    }

    /**
     * Create a new DateParseResult.
     *
     * @param ?\DateTimeImmutable $date          The parsed date (null if parsing failed)
     * @param ?string             $time          The time portion as string (e.g., "14:00", null if no time)
     * @param string              $originalText  The original text that was matched
     * @param int                 $startPosition Start position of the match in the input string
     * @param int                 $endPosition   End position of the match in the input string
     * @param bool                $hasTime       Whether a time component was parsed
     */
    public static function create(
        ?\DateTimeImmutable $date,
        ?string $time,
        string $originalText,
        int $startPosition,
        int $endPosition,
        bool $hasTime = false,
    ): self {
        return new self(
            date: $date,
            time: $time,
            originalText: $originalText,
            startPosition: $startPosition,
            endPosition: $endPosition,
            hasTime: $hasTime,
        );
    }

    /**
     * Serialize the result to an array.
     *
     * @return array{
     *     date: ?string,
     *     time: ?string,
     *     originalText: string,
     *     startPosition: int,
     *     endPosition: int,
     *     hasTime: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date?->format(\DateTimeImmutable::ATOM),
            'time' => $this->time,
            'originalText' => $this->originalText,
            'startPosition' => $this->startPosition,
            'endPosition' => $this->endPosition,
            'hasTime' => $this->hasTime,
        ];
    }

    /**
     * Deserialize a result from an array.
     *
     * @param array{
     *     date: ?string,
     *     time: ?string,
     *     originalText: string,
     *     startPosition: int,
     *     endPosition: int,
     *     hasTime: bool
     * } $data
     */
    public static function fromArray(array $data): self
    {
        $requiredKeys = ['originalText', 'startPosition', 'endPosition', 'hasTime'];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $data)) {
                throw new \InvalidArgumentException(sprintf('Missing required key "%s" in date parse result data', $key));
            }
        }

        return new self(
            date: isset($data['date']) ? new \DateTimeImmutable($data['date']) : null,
            time: $data['time'] ?? null,
            originalText: $data['originalText'],
            startPosition: $data['startPosition'],
            endPosition: $data['endPosition'],
            hasTime: $data['hasTime'],
        );
    }
}
