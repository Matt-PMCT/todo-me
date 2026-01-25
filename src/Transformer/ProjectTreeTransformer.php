<?php

declare(strict_types=1);

namespace App\Transformer;

use App\Entity\Project;

/**
 * Transforms flat project lists into hierarchical tree structures.
 */
final class ProjectTreeTransformer
{
    /**
     * Transform a flat array of projects into a nested tree structure.
     *
     * @param Project[]                                        $projects   Flat array of projects
     * @param array<string, array{total: int, completed: int}> $taskCounts Optional task counts indexed by project ID
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     description: ?string,
     *     color: string,
     *     icon: ?string,
     *     position: int,
     *     isArchived: bool,
     *     showChildrenTasks: bool,
     *     depth: int,
     *     taskCount: int,
     *     completedTaskCount: int,
     *     pendingTaskCount: int,
     *     children: array
     * }>
     */
    public function transformToTree(array $projects, array $taskCounts = []): array
    {
        // Build a map of projects by ID for quick lookup
        $projectMap = [];
        foreach ($projects as $project) {
            $projectMap[$project->getId()] = $project;
        }

        // Build tree structure
        $tree = [];
        $childrenMap = [];

        // First pass: group children by parent ID
        // Use empty string for root-level projects to avoid conflicts with project IDs
        foreach ($projects as $project) {
            $parentId = $project->getParent()?->getId() ?? '';
            if (!isset($childrenMap[$parentId])) {
                $childrenMap[$parentId] = [];
            }
            $childrenMap[$parentId][] = $project;
        }

        // Sort children by position, then by ID for stability
        foreach ($childrenMap as $key => $children) {
            usort($childrenMap[$key], function (Project $a, Project $b) {
                $posCompare = $a->getPosition() <=> $b->getPosition();
                if ($posCompare !== 0) {
                    return $posCompare;
                }

                return $a->getId() <=> $b->getId();
            });
        }

        // Build tree recursively starting from root projects (those with no parent)
        $rootProjects = $childrenMap[''] ?? [];
        foreach ($rootProjects as $project) {
            $tree[] = $this->buildNode($project, $childrenMap, $taskCounts);
        }

        return $tree;
    }

    /**
     * Build a single node with its children recursively.
     *
     * @param Project                                          $project     The project to transform
     * @param array<string, Project[]>                         $childrenMap Map of parent ID to children
     * @param array<string, array{total: int, completed: int}> $taskCounts  Task counts by project ID
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     description: ?string,
     *     color: string,
     *     icon: ?string,
     *     position: int,
     *     isArchived: bool,
     *     showChildrenTasks: bool,
     *     depth: int,
     *     taskCount: int,
     *     completedTaskCount: int,
     *     pendingTaskCount: int,
     *     children: array
     * }
     */
    private function buildNode(Project $project, array $childrenMap, array $taskCounts): array
    {
        $projectId = $project->getId();
        $taskCount = $taskCounts[$projectId] ?? ['total' => 0, 'completed' => 0];

        $children = [];
        if (isset($childrenMap[$projectId])) {
            foreach ($childrenMap[$projectId] as $child) {
                $children[] = $this->buildNode($child, $childrenMap, $taskCounts);
            }
        }

        return [
            'id' => $projectId,
            'name' => $project->getName(),
            'description' => $project->getDescription(),
            'color' => $project->getColor(),
            'icon' => $project->getIcon(),
            'position' => $project->getPosition(),
            'isArchived' => $project->isArchived(),
            'showChildrenTasks' => $project->isShowChildrenTasks(),
            'depth' => $project->getDepth(),
            'taskCount' => $taskCount['total'],
            'completedTaskCount' => $taskCount['completed'],
            'pendingTaskCount' => $taskCount['total'] - $taskCount['completed'],
            'children' => $children,
        ];
    }

    /**
     * Transform a single project node with optional children.
     *
     * @param Project                                $project   The project to transform
     * @param array<int, array>                      $children  Pre-built children nodes
     * @param array{total: int, completed: int}|null $taskCount Task count for this project
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     description: ?string,
     *     color: string,
     *     icon: ?string,
     *     position: int,
     *     isArchived: bool,
     *     showChildrenTasks: bool,
     *     depth: int,
     *     taskCount: int,
     *     completedTaskCount: int,
     *     pendingTaskCount: int,
     *     children: array
     * }
     */
    public function transformNode(Project $project, array $children = [], ?array $taskCount = null): array
    {
        $total = $taskCount['total'] ?? 0;
        $completed = $taskCount['completed'] ?? 0;

        return [
            'id' => $project->getId(),
            'name' => $project->getName(),
            'description' => $project->getDescription(),
            'color' => $project->getColor(),
            'icon' => $project->getIcon(),
            'position' => $project->getPosition(),
            'isArchived' => $project->isArchived(),
            'showChildrenTasks' => $project->isShowChildrenTasks(),
            'depth' => $project->getDepth(),
            'taskCount' => $total,
            'completedTaskCount' => $completed,
            'pendingTaskCount' => $total - $completed,
            'children' => $children,
        ];
    }
}
