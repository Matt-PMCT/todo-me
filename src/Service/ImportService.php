<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Project;
use App\Entity\Tag;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Repository\TagRepository;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for importing data from various formats.
 *
 * Supports:
 * - JSON (our native export format)
 * - Todoist export format
 * - CSV format
 */
final class ImportService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProjectRepository $projectRepository,
        private readonly TagRepository $tagRepository,
        private readonly TaskRepository $taskRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Import from JSON format (our native export format).
     *
     * @param User                 $user The user importing data
     * @param array<string, mixed> $data The JSON data
     *
     * @return array{tasks: int, projects: int, tags: int}
     */
    public function importJson(User $user, array $data): array
    {
        $stats = ['tasks' => 0, 'projects' => 0, 'tags' => 0];

        $this->entityManager->beginTransaction();

        try {
            // Build caches for name-based lookups
            $projectCache = []; // name => Project
            $tagCache = []; // name => Tag

            // Import projects first (they may be referenced by tasks)
            if (isset($data['projects']) && is_array($data['projects'])) {
                foreach ($data['projects'] as $projectData) {
                    if (!is_array($projectData)) {
                        continue;
                    }

                    try {
                        $project = $this->importProject($user, $projectData, $projectCache);
                        if ($project !== null) {
                            $projectCache[strtolower($project->getName())] = $project;
                            $stats['projects']++;
                        }
                    } catch (\Exception $e) {
                        $this->logger->warning('Failed to import project', [
                            'data' => $projectData,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Import tags second
            if (isset($data['tags']) && is_array($data['tags'])) {
                foreach ($data['tags'] as $tagData) {
                    if (!is_array($tagData)) {
                        continue;
                    }

                    try {
                        $tag = $this->importTag($user, $tagData, $tagCache);
                        if ($tag !== null) {
                            $tagCache[strtolower($tag->getName())] = $tag;
                            $stats['tags']++;
                        }
                    } catch (\Exception $e) {
                        $this->logger->warning('Failed to import tag', [
                            'data' => $tagData,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Import tasks last (they reference projects and tags)
            if (isset($data['tasks']) && is_array($data['tasks'])) {
                foreach ($data['tasks'] as $taskData) {
                    if (!is_array($taskData)) {
                        continue;
                    }

                    try {
                        $task = $this->importTask($user, $taskData, $projectCache, $tagCache);
                        if ($task !== null) {
                            $stats['tasks']++;
                        }
                    } catch (\Exception $e) {
                        $this->logger->warning('Failed to import task', [
                            'data' => $taskData,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            return $stats;
        } catch (\Throwable $e) {
            $this->entityManager->rollback();

            throw $e;
        }
    }

    /**
     * Import from Todoist export format.
     *
     * Todoist format: {items: [...], projects: [...], labels: [...]}
     *
     * @param User                 $user The user importing data
     * @param array<string, mixed> $data The Todoist data
     *
     * @return array{tasks: int, projects: int, tags: int}
     */
    public function importTodoist(User $user, array $data): array
    {
        $stats = ['tasks' => 0, 'projects' => 0, 'tags' => 0];

        $this->entityManager->beginTransaction();

        try {
            // Map Todoist projects (projects array)
            $projectMap = []; // todoist_id => our_project
            $projectNameCache = []; // name => Project
            if (isset($data['projects']) && is_array($data['projects'])) {
                foreach ($data['projects'] as $project) {
                    if (!is_array($project)) {
                        continue;
                    }

                    try {
                        $imported = $this->importTodoistProject($user, $project, $projectNameCache);
                        if ($imported !== null) {
                            $projectId = $project['id'] ?? null;
                            if ($projectId !== null) {
                                $projectMap[$projectId] = $imported;
                            }
                            $projectNameCache[strtolower($imported->getName())] = $imported;
                            $stats['projects']++;
                        }
                    } catch (\Exception $e) {
                        $this->logger->warning('Failed to import Todoist project', [
                            'data' => $project,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Map Todoist labels -> our tags
            $tagMap = []; // label_name => our_tag
            $tagCache = []; // name => Tag
            if (isset($data['labels']) && is_array($data['labels'])) {
                foreach ($data['labels'] as $label) {
                    if (!is_array($label)) {
                        continue;
                    }

                    try {
                        $imported = $this->importTodoistLabel($user, $label, $tagCache);
                        if ($imported !== null) {
                            $labelName = $label['name'] ?? '';
                            if ($labelName !== '') {
                                $tagMap[$labelName] = $imported;
                            }
                            $tagCache[strtolower($imported->getName())] = $imported;
                            $stats['tags']++;
                        }
                    } catch (\Exception $e) {
                        $this->logger->warning('Failed to import Todoist label', [
                            'data' => $label,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Import Todoist items -> our tasks
            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    try {
                        $task = $this->importTodoistItem($user, $item, $projectMap, $tagMap);
                        if ($task !== null) {
                            $stats['tasks']++;
                        }
                    } catch (\Exception $e) {
                        $this->logger->warning('Failed to import Todoist item', [
                            'data' => $item,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            return $stats;
        } catch (\Throwable $e) {
            $this->entityManager->rollback();

            throw $e;
        }
    }

    /**
     * Import from CSV format.
     *
     * Expected headers: title,description,status,priority,dueDate,project,tags
     *
     * @param User   $user       The user importing data
     * @param string $csvContent The CSV content
     *
     * @return array{tasks: int, projects: int, tags: int}
     */
    public function importCsv(User $user, string $csvContent): array
    {
        $stats = ['tasks' => 0, 'projects' => 0, 'tags' => 0];

        $lines = str_getcsv($csvContent, "\n", '"', '\\');
        if (empty($lines)) {
            return $stats;
        }

        // Parse headers from first line
        $headerLine = array_shift($lines);
        if ($headerLine === null || trim($headerLine) === '') {
            return $stats;
        }

        $headers = str_getcsv($headerLine, ',', '"', '\\');

        // Normalize headers
        $headers = array_map(fn ($h) => strtolower(trim($h)), $headers);

        $this->entityManager->beginTransaction();

        try {
            // Build project and tag caches
            $projectCache = []; // name => project
            $tagCache = []; // name => tag

            foreach ($lines as $line) {
                if (empty(trim($line))) {
                    continue;
                }

                $row = str_getcsv($line, ',', '"', '\\');

                // Skip if row doesn't have enough columns
                if (count($row) < count($headers)) {
                    // Pad with empty values
                    $row = array_pad($row, count($headers), '');
                }

                $data = array_combine($headers, $row);
                if ($data === false) {
                    continue;
                }

                try {
                    $this->importCsvRow($user, $data, $projectCache, $tagCache, $stats);
                } catch (\Exception $e) {
                    $this->logger->warning('Failed to import CSV row', [
                        'data' => $data,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            return $stats;
        } catch (\Throwable $e) {
            $this->entityManager->rollback();

            throw $e;
        }
    }

    /**
     * Import a project from JSON data.
     *
     * @param User                   $user         The owner
     * @param array<string, mixed>   $data         The project data
     * @param array<string, Project> $projectCache Cache of already imported projects
     *
     * @return Project|null The imported or existing project
     */
    private function importProject(User $user, array $data, array &$projectCache): ?Project
    {
        $name = $data['name'] ?? null;
        if ($name === null || trim($name) === '') {
            return null;
        }

        $name = trim($name);
        $nameLower = strtolower($name);

        // Check cache first
        if (isset($projectCache[$nameLower])) {
            return $projectCache[$nameLower];
        }

        // Check if project with same name exists for user
        $existing = $this->projectRepository->findByNameInsensitive($user, $name);
        if ($existing !== null) {
            $projectCache[$nameLower] = $existing;

            return $existing;
        }

        // Create new project
        $project = new Project();
        $project->setOwner($user);
        $project->setName($name);

        if (isset($data['description']) && is_string($data['description'])) {
            $project->setDescription($data['description']);
        }

        if (isset($data['isArchived']) && is_bool($data['isArchived'])) {
            $project->setIsArchived($data['isArchived']);
            if ($data['isArchived']) {
                $project->setArchivedAt(new \DateTimeImmutable());
            }
        }

        if (isset($data['color']) && is_string($data['color']) && preg_match('/^#[0-9A-Fa-f]{6}$/', $data['color'])) {
            $project->setColor($data['color']);
        }

        // Set position to max + 1
        $maxPosition = $this->projectRepository->getMaxPositionInParent($user, null);
        $project->setPosition($maxPosition + 1);

        $this->entityManager->persist($project);

        $projectCache[$nameLower] = $project;

        return $project;
    }

    /**
     * Import a tag from JSON data.
     *
     * @param User                 $user     The owner
     * @param array<string, mixed> $data     The tag data
     * @param array<string, Tag>   $tagCache Cache of already imported tags
     *
     * @return Tag|null The imported or existing tag
     */
    private function importTag(User $user, array $data, array &$tagCache): ?Tag
    {
        $name = $data['name'] ?? null;
        if ($name === null || trim($name) === '') {
            return null;
        }

        $name = strtolower(trim($name));

        // Check cache first
        if (isset($tagCache[$name])) {
            return $tagCache[$name];
        }

        // Check if tag with same name exists for user
        $existing = $this->tagRepository->findByNameInsensitive($user, $name);
        if ($existing !== null) {
            $tagCache[$name] = $existing;

            return $existing;
        }

        // Create new tag
        $tag = new Tag();
        $tag->setOwner($user);
        $tag->setName($name);

        if (isset($data['color']) && is_string($data['color']) && preg_match('/^#[0-9A-Fa-f]{6}$/', $data['color'])) {
            $tag->setColor($data['color']);
        }

        $this->entityManager->persist($tag);

        $tagCache[$name] = $tag;

        return $tag;
    }

    /**
     * Import a task from JSON data.
     *
     * @param User                   $user         The owner
     * @param array<string, mixed>   $data         The task data
     * @param array<string, Project> $projectCache Cache of projects by name
     * @param array<string, Tag>     $tagCache     Cache of tags by name
     *
     * @return Task|null The imported task
     */
    private function importTask(User $user, array $data, array &$projectCache, array &$tagCache): ?Task
    {
        $title = $data['title'] ?? null;
        if ($title === null || trim($title) === '') {
            return null;
        }

        $task = new Task();
        $task->setOwner($user);
        $task->setTitle(trim($title));

        // Description
        if (isset($data['description']) && is_string($data['description'])) {
            $task->setDescription($data['description']);
        }

        // Status (default: pending)
        $status = $data['status'] ?? Task::STATUS_PENDING;
        if (in_array($status, Task::STATUSES, true)) {
            $task->setStatus($status);
        }

        // Priority (default: 2)
        $priority = $data['priority'] ?? Task::PRIORITY_DEFAULT;
        if (is_int($priority) && $priority >= Task::PRIORITY_MIN && $priority <= Task::PRIORITY_MAX) {
            $task->setPriority($priority);
        }

        // Due date
        if (isset($data['dueDate']) && is_string($data['dueDate']) && $data['dueDate'] !== '') {
            try {
                $dueDate = new \DateTimeImmutable($data['dueDate']);
                $task->setDueDate($dueDate);
            } catch (\Exception $e) {
                // Ignore invalid dates
            }
        }

        // Project (resolve by name)
        if (isset($data['project']) && is_string($data['project']) && $data['project'] !== '') {
            $projectName = strtolower(trim($data['project']));
            $project = $projectCache[$projectName] ?? $this->projectRepository->findByNameInsensitive($user, $projectName);
            if ($project !== null) {
                $task->setProject($project);
                $projectCache[$projectName] = $project;
            }
        }

        // Tags (resolve by name)
        if (isset($data['tags']) && is_array($data['tags'])) {
            foreach ($data['tags'] as $tagName) {
                if (!is_string($tagName) || trim($tagName) === '') {
                    continue;
                }
                $tagName = strtolower(trim($tagName));
                $tag = $tagCache[$tagName] ?? $this->tagRepository->findByNameInsensitive($user, $tagName);
                if ($tag !== null) {
                    $task->addTag($tag);
                    $tagCache[$tagName] = $tag;
                } else {
                    // Create the tag if it doesn't exist
                    $tag = new Tag();
                    $tag->setOwner($user);
                    $tag->setName($tagName);
                    $this->entityManager->persist($tag);
                    $tagCache[$tagName] = $tag;
                    $task->addTag($tag);
                }
            }
        }

        // Set position to max + 1
        $maxPosition = $this->taskRepository->getMaxPosition($user, $task->getProject());
        $task->setPosition($maxPosition + 1);

        $this->entityManager->persist($task);

        return $task;
    }

    /**
     * Import a Todoist project.
     *
     * @param User                   $user         The owner
     * @param array<string, mixed>   $data         The Todoist project data
     * @param array<string, Project> $projectCache Cache of projects by name
     *
     * @return Project|null The imported or existing project
     */
    private function importTodoistProject(User $user, array $data, array &$projectCache): ?Project
    {
        $name = $data['name'] ?? null;
        if ($name === null || trim($name) === '') {
            return null;
        }

        $name = trim($name);
        $nameLower = strtolower($name);

        // Check cache first
        if (isset($projectCache[$nameLower])) {
            return $projectCache[$nameLower];
        }

        // Check if project with same name exists for user
        $existing = $this->projectRepository->findByNameInsensitive($user, $name);
        if ($existing !== null) {
            $projectCache[$nameLower] = $existing;

            return $existing;
        }

        // Create new project
        $project = new Project();
        $project->setOwner($user);
        $project->setName($name);

        // Todoist color mapping (Todoist uses color names or numbers)
        if (isset($data['color'])) {
            $color = $this->mapTodoistColor($data['color']);
            if ($color !== null) {
                $project->setColor($color);
            }
        }

        // Set position to max + 1
        $maxPosition = $this->projectRepository->getMaxPositionInParent($user, null);
        $project->setPosition($maxPosition + 1);

        $this->entityManager->persist($project);

        $projectCache[$nameLower] = $project;

        return $project;
    }

    /**
     * Import a Todoist label as a tag.
     *
     * @param User                 $user     The owner
     * @param array<string, mixed> $data     The Todoist label data
     * @param array<string, Tag>   $tagCache Cache of tags by name
     *
     * @return Tag|null The imported or existing tag
     */
    private function importTodoistLabel(User $user, array $data, array &$tagCache): ?Tag
    {
        $name = $data['name'] ?? null;
        if ($name === null || trim($name) === '') {
            return null;
        }

        $name = strtolower(trim($name));

        // Check cache first
        if (isset($tagCache[$name])) {
            return $tagCache[$name];
        }

        // Check if tag with same name exists for user
        $existing = $this->tagRepository->findByNameInsensitive($user, $name);
        if ($existing !== null) {
            $tagCache[$name] = $existing;

            return $existing;
        }

        // Create new tag
        $tag = new Tag();
        $tag->setOwner($user);
        $tag->setName($name);

        // Todoist color mapping
        if (isset($data['color'])) {
            $color = $this->mapTodoistColor($data['color']);
            if ($color !== null) {
                $tag->setColor($color);
            }
        }

        $this->entityManager->persist($tag);

        $tagCache[$name] = $tag;

        return $tag;
    }

    /**
     * Import a Todoist item as a task.
     *
     * @param User                       $user       The owner
     * @param array<string, mixed>       $item       The Todoist item data
     * @param array<int|string, Project> $projectMap Map of Todoist project IDs to our projects
     * @param array<string, Tag>         $tagMap     Map of Todoist label names to our tags
     *
     * @return Task|null The imported task
     */
    private function importTodoistItem(User $user, array $item, array $projectMap, array $tagMap): ?Task
    {
        // Todoist uses 'content' for task title
        $title = $item['content'] ?? null;
        if ($title === null || trim($title) === '') {
            return null;
        }

        $task = new Task();
        $task->setOwner($user);
        $task->setTitle(trim($title));

        // Description
        if (isset($item['description']) && is_string($item['description'])) {
            $task->setDescription($item['description']);
        }

        // Status - Todoist uses 'checked' or 'completed'
        $isCompleted = ($item['checked'] ?? false) || ($item['completed'] ?? false);
        if ($isCompleted) {
            $task->setStatus(Task::STATUS_COMPLETED);
        }

        // Priority - Todoist uses 1-4 where 4 is highest (opposite of our 0-4)
        // Todoist: 1=lowest, 4=highest
        // Our system: 0=lowest, 4=highest
        $todoistPriority = $item['priority'] ?? 1;
        if (is_int($todoistPriority)) {
            // Map: Todoist 4->4, 3->3, 2->2, 1->1 (actually same in latest Todoist API)
            $priority = min(max($todoistPriority - 1, Task::PRIORITY_MIN), Task::PRIORITY_MAX);
            $task->setPriority($priority);
        }

        // Due date - Todoist uses 'due' object with 'date' field
        if (isset($item['due']['date']) && is_string($item['due']['date'])) {
            try {
                $dueDate = new \DateTimeImmutable($item['due']['date']);
                $task->setDueDate($dueDate);
            } catch (\Exception $e) {
                // Ignore invalid dates
            }
        }

        // Project
        $projectId = $item['project_id'] ?? null;
        if ($projectId !== null && isset($projectMap[$projectId])) {
            $task->setProject($projectMap[$projectId]);
        }

        // Labels/Tags - Todoist uses 'labels' array of label names
        if (isset($item['labels']) && is_array($item['labels'])) {
            foreach ($item['labels'] as $labelName) {
                if (!is_string($labelName)) {
                    continue;
                }
                if (isset($tagMap[$labelName])) {
                    $task->addTag($tagMap[$labelName]);
                }
            }
        }

        // Set position to max + 1
        $maxPosition = $this->taskRepository->getMaxPosition($user, $task->getProject());
        $task->setPosition($maxPosition + 1);

        $this->entityManager->persist($task);

        return $task;
    }

    /**
     * Import a single CSV row as a task.
     *
     * @param User                                        $user         The owner
     * @param array<string, string>                       $data         The row data
     * @param array<string, Project>                      $projectCache Cache of projects by name
     * @param array<string, Tag>                          $tagCache     Cache of tags by name
     * @param array{tasks: int, projects: int, tags: int} $stats        Import statistics (modified by reference)
     */
    private function importCsvRow(User $user, array $data, array &$projectCache, array &$tagCache, array &$stats): void
    {
        $title = $data['title'] ?? null;
        if ($title === null || trim($title) === '') {
            return;
        }

        $task = new Task();
        $task->setOwner($user);
        $task->setTitle(trim($title));

        // Description
        if (isset($data['description']) && trim($data['description']) !== '') {
            $task->setDescription(trim($data['description']));
        }

        // Status (default: pending)
        if (isset($data['status']) && trim($data['status']) !== '') {
            $status = trim($data['status']);
            if (in_array($status, Task::STATUSES, true)) {
                $task->setStatus($status);
            }
        }

        // Priority (default: 2)
        if (isset($data['priority']) && trim($data['priority']) !== '') {
            $priority = (int) trim($data['priority']);
            if ($priority >= Task::PRIORITY_MIN && $priority <= Task::PRIORITY_MAX) {
                $task->setPriority($priority);
            }
        }

        // Due date (support various formats)
        $dueDateField = $data['duedate'] ?? $data['due_date'] ?? $data['due'] ?? null;
        if ($dueDateField !== null && trim($dueDateField) !== '') {
            try {
                $dueDate = new \DateTimeImmutable(trim($dueDateField));
                $task->setDueDate($dueDate);
            } catch (\Exception $e) {
                // Ignore invalid dates
            }
        }

        // Project (resolve by name, create if doesn't exist)
        if (isset($data['project']) && trim($data['project']) !== '') {
            $projectName = trim($data['project']);
            $projectNameLower = strtolower($projectName);

            $project = $projectCache[$projectNameLower] ?? null;

            if ($project === null) {
                $project = $this->projectRepository->findByNameInsensitive($user, $projectName);
            }

            if ($project === null) {
                // Create new project
                $project = new Project();
                $project->setOwner($user);
                $project->setName($projectName);
                $maxPosition = $this->projectRepository->getMaxPositionInParent($user, null);
                $project->setPosition($maxPosition + 1);
                $this->entityManager->persist($project);
                $stats['projects']++;
            }

            $projectCache[$projectNameLower] = $project;
            $task->setProject($project);
        }

        // Tags (comma-separated)
        if (isset($data['tags']) && trim($data['tags']) !== '') {
            $tagNames = array_map('trim', explode(',', $data['tags']));
            foreach ($tagNames as $tagName) {
                if ($tagName === '') {
                    continue;
                }

                $tagNameLower = strtolower($tagName);
                $tag = $tagCache[$tagNameLower] ?? null;

                if ($tag === null) {
                    $tag = $this->tagRepository->findByNameInsensitive($user, $tagName);
                }

                if ($tag === null) {
                    // Create new tag
                    $tag = new Tag();
                    $tag->setOwner($user);
                    $tag->setName($tagNameLower);
                    $this->entityManager->persist($tag);
                    $stats['tags']++;
                }

                $tagCache[$tagNameLower] = $tag;
                $task->addTag($tag);
            }
        }

        // Set position to max + 1
        $maxPosition = $this->taskRepository->getMaxPosition($user, $task->getProject());
        $task->setPosition($maxPosition + 1);

        $this->entityManager->persist($task);
        $stats['tasks']++;
    }

    /**
     * Map Todoist color to hex color.
     *
     * @param mixed $todoistColor Todoist color (name or number)
     *
     * @return string|null Hex color code or null
     */
    private function mapTodoistColor(mixed $todoistColor): ?string
    {
        // Todoist color mapping (name -> hex)
        $colorMap = [
            'berry_red' => '#B8255F',
            'red' => '#DB4035',
            'orange' => '#FF9933',
            'yellow' => '#FAD000',
            'olive_green' => '#AFB83B',
            'lime_green' => '#7ECC49',
            'green' => '#299438',
            'mint_green' => '#6ACCBC',
            'teal' => '#158FAD',
            'sky_blue' => '#14AAF5',
            'light_blue' => '#96C3EB',
            'blue' => '#4073FF',
            'grape' => '#884DFF',
            'violet' => '#AF38EB',
            'lavender' => '#EB96EB',
            'magenta' => '#E05194',
            'salmon' => '#FF8D85',
            'charcoal' => '#808080',
            'grey' => '#B8B8B8',
            'taupe' => '#CCAC93',
        ];

        // Todoist numeric color mapping (older API versions)
        $numericColorMap = [
            30 => '#B8255F', // berry_red
            31 => '#DB4035', // red
            32 => '#FF9933', // orange
            33 => '#FAD000', // yellow
            34 => '#AFB83B', // olive_green
            35 => '#7ECC49', // lime_green
            36 => '#299438', // green
            37 => '#6ACCBC', // mint_green
            38 => '#158FAD', // teal
            39 => '#14AAF5', // sky_blue
            40 => '#96C3EB', // light_blue
            41 => '#4073FF', // blue
            42 => '#884DFF', // grape
            43 => '#AF38EB', // violet
            44 => '#EB96EB', // lavender
            45 => '#E05194', // magenta
            46 => '#FF8D85', // salmon
            47 => '#808080', // charcoal
            48 => '#B8B8B8', // grey
            49 => '#CCAC93', // taupe
        ];

        if (is_string($todoistColor)) {
            // Check if it's already a hex color
            if (preg_match('/^#[0-9A-Fa-f]{6}$/', $todoistColor)) {
                return strtoupper($todoistColor);
            }

            // Look up by name
            return $colorMap[$todoistColor] ?? null;
        }

        if (is_int($todoistColor)) {
            return $numericColorMap[$todoistColor] ?? null;
        }

        return null;
    }
}
