<?php

declare(strict_types=1);

namespace App\Entity;

use App\Exception\ProjectCannotBeOwnParentException;
use App\Exception\ProjectCircularReferenceException;
use App\Exception\ProjectHierarchyTooDeepException;
use App\Interface\UserOwnedInterface;
use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\Table(name: 'projects')]
#[ORM\Index(columns: ['owner_id'], name: 'idx_projects_owner')]
#[ORM\Index(columns: ['is_archived'], name: 'idx_projects_archived')]
#[ORM\Index(columns: ['parent_id'], name: 'idx_projects_parent')]
#[ORM\Index(columns: ['position'], name: 'idx_projects_position')]
#[ORM\Index(columns: ['deleted_at'], name: 'idx_projects_deleted_at')]
#[ORM\HasLifecycleCallbacks]
class Project implements UserOwnedInterface
{
    public const MAX_HIERARCHY_DEPTH = 50;

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?string $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::BOOLEAN, name: 'is_archived', options: ['default' => false])]
    private bool $isArchived = false;

    #[ORM\Column(type: Types::STRING, length: 7, options: ['default' => '#808080'])]
    private string $color = '#808080';

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, name: 'archived_at')]
    private ?\DateTimeImmutable $archivedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, name: 'deleted_at')]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column(type: Types::BOOLEAN, name: 'show_children_tasks', options: ['default' => true])]
    private bool $showChildrenTasks = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'projects')]
    #[ORM\JoinColumn(name: 'owner_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', nullable: true, onDelete: 'CASCADE')]
    private ?Project $parent = null;

    /**
     * @var Collection<int, Project>
     */
    #[ORM\OneToMany(targetEntity: Project::class, mappedBy: 'parent')]
    private Collection $children;

    /**
     * @var Collection<int, Task>
     */
    #[ORM\OneToMany(targetEntity: Task::class, mappedBy: 'project', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $tasks;

    public function __construct()
    {
        $this->tasks = new ArrayCollection();
        $this->children = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function isArchived(): bool
    {
        return $this->isArchived;
    }

    public function setIsArchived(bool $isArchived): static
    {
        $this->isArchived = $isArchived;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    /**
     * @return Collection<int, Task>
     */
    public function getTasks(): Collection
    {
        return $this->tasks;
    }

    public function addTask(Task $task): static
    {
        if (!$this->tasks->contains($task)) {
            $this->tasks->add($task);
            $task->setProject($this);
        }

        return $this;
    }

    public function removeTask(Task $task): static
    {
        if ($this->tasks->removeElement($task)) {
            if ($task->getProject() === $this) {
                $task->setProject(null);
            }
        }

        return $this;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getArchivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function setArchivedAt(?\DateTimeImmutable $archivedAt): static
    {
        $this->archivedAt = $archivedAt;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    /**
     * Check if this project has been soft-deleted.
     */
    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * Soft-delete this project by setting the deletedAt timestamp.
     */
    public function softDelete(): static
    {
        $this->deletedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * Restore a soft-deleted project by clearing the deletedAt timestamp.
     */
    public function restore(): static
    {
        $this->deletedAt = null;

        return $this;
    }

    public function isShowChildrenTasks(): bool
    {
        return $this->showChildrenTasks;
    }

    public function setShowChildrenTasks(bool $showChildrenTasks): static
    {
        $this->showChildrenTasks = $showChildrenTasks;

        return $this;
    }

    public function getParent(): ?Project
    {
        return $this->parent;
    }

    public function setParent(?Project $parent): static
    {
        if ($parent === null) {
            $this->parent = null;
            return $this;
        }

        if ($parent->getId() !== null && $parent->getId() === $this->getId()) {
            throw ProjectCannotBeOwnParentException::create($this->getId() ?? '');
        }

        if ($this->getId() !== null && $parent->isDescendantOf($this)) {
            throw ProjectCircularReferenceException::create($this->getId() ?? '', $parent->getId() ?? '');
        }

        // Validate parent has the same owner
        if ($parent->getOwner() !== $this->getOwner()) {
            throw new \InvalidArgumentException('Parent project must have the same owner');
        }

        // Check depth limit
        $newDepth = $parent->getDepth() + 1;
        if ($newDepth >= self::MAX_HIERARCHY_DEPTH) {
            throw ProjectHierarchyTooDeepException::create($this->getId() ?? '', self::MAX_HIERARCHY_DEPTH);
        }

        $this->parent = $parent;

        return $this;
    }

    /**
     * @return Collection<int, Project>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(Project $child): static
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }

        return $this;
    }

    public function removeChild(Project $child): static
    {
        if ($this->children->removeElement($child)) {
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }

        return $this;
    }

    /**
     * Get the full path of this project by traversing its parent chain.
     *
     * For example, if this project is "Backend" with parent "Work",
     * returns "Work/Backend".
     */
    public function getFullPath(): string
    {
        $parts = [];
        $current = $this;

        while ($current !== null) {
            array_unshift($parts, $current->getName());
            $current = $current->getParent();
        }

        return implode('/', $parts);
    }

    /**
     * Get the depth of this project in the hierarchy.
     *
     * Root projects (no parent) have depth 0.
     */
    public function getDepth(): int
    {
        $depth = 0;
        $current = $this->parent;

        while ($current !== null) {
            $depth++;
            $current = $current->getParent();
        }

        return $depth;
    }

    /**
     * Get all ancestors of this project from root to immediate parent.
     *
     * @return Project[]
     */
    public function getAncestors(): array
    {
        $ancestors = [];
        $current = $this->parent;

        while ($current !== null) {
            array_unshift($ancestors, $current);
            $current = $current->getParent();
        }

        return $ancestors;
    }

    /**
     * Get the path of project names from root to this project.
     *
     * @return string[]
     */
    public function getPath(): array
    {
        $path = [];
        $current = $this;

        while ($current !== null) {
            array_unshift($path, $current->getName());
            $current = $current->getParent();
        }

        return $path;
    }

    /**
     * Check if this project is a descendant of the given ancestor.
     */
    public function isDescendantOf(Project $ancestor): bool
    {
        $current = $this->parent;

        while ($current !== null) {
            if ($current->getId() === $ancestor->getId()) {
                return true;
            }
            $current = $current->getParent();
        }

        return false;
    }

    /**
     * Check if this project is an ancestor of the given descendant.
     */
    public function isAncestorOf(Project $descendant): bool
    {
        return $descendant->isDescendantOf($this);
    }

    /**
     * Get all descendants of this project recursively.
     *
     * @return Project[]
     */
    public function getAllDescendants(): array
    {
        $descendants = [];

        foreach ($this->children as $child) {
            $descendants[] = $child;
            $descendants = array_merge($descendants, $child->getAllDescendants());
        }

        return $descendants;
    }

    /**
     * Get the path with full details for each ancestor.
     *
     * @return array<array{id: string|null, name: string, isArchived: bool}>
     */
    public function getPathDetails(): array
    {
        $path = [];
        $ancestors = $this->getAncestors();

        foreach ($ancestors as $ancestor) {
            $path[] = [
                'id' => $ancestor->getId(),
                'name' => $ancestor->getName(),
                'isArchived' => $ancestor->isArchived(),
            ];
        }

        $path[] = [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'isArchived' => $this->isArchived(),
        ];

        return $path;
    }
}
