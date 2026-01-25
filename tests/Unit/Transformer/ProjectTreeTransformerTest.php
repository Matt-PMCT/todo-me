<?php

declare(strict_types=1);

namespace App\Tests\Unit\Transformer;

use App\Tests\Unit\UnitTestCase;
use App\Transformer\ProjectTreeTransformer;

class ProjectTreeTransformerTest extends UnitTestCase
{
    private ProjectTreeTransformer $transformer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transformer = new ProjectTreeTransformer();
    }

    // ========================================
    // transformToTree Tests
    // ========================================

    public function testTransformToTreeWithEmptyArray(): void
    {
        $result = $this->transformer->transformToTree([]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testTransformToTreeWithSingleRootProject(): void
    {
        $project = $this->createProjectWithId('project-1');
        $project->setName('Project 1');
        $project->setColor('#FF0000');

        $result = $this->transformer->transformToTree([$project]);

        $this->assertCount(1, $result);
        $this->assertEquals('project-1', $result[0]['id']);
        $this->assertEquals('Project 1', $result[0]['name']);
        $this->assertEquals('#FF0000', $result[0]['color']);
        $this->assertEmpty($result[0]['children']);
    }

    public function testTransformToTreeWithNestedProjects(): void
    {
        $parent = $this->createProjectWithId('parent-1');
        $parent->setName('Parent');

        $child = $this->createProjectWithId('child-1');
        $child->setName('Child');
        $parent->addChild($child);

        $result = $this->transformer->transformToTree([$parent, $child]);

        $this->assertCount(1, $result);
        $this->assertEquals('parent-1', $result[0]['id']);
        $this->assertCount(1, $result[0]['children']);
        $this->assertEquals('child-1', $result[0]['children'][0]['id']);
    }

    public function testTransformToTreeWithMultipleRootProjects(): void
    {
        $project1 = $this->createProjectWithId('project-1');
        $project1->setName('Project 1');
        $project1->setPosition(0);

        $project2 = $this->createProjectWithId('project-2');
        $project2->setName('Project 2');
        $project2->setPosition(1);

        $result = $this->transformer->transformToTree([$project1, $project2]);

        $this->assertCount(2, $result);
        $this->assertEquals('project-1', $result[0]['id']);
        $this->assertEquals('project-2', $result[1]['id']);
    }

    public function testTransformToTreeOrdersChildrenByPosition(): void
    {
        $parent = $this->createProjectWithId('parent-1');
        $parent->setName('Parent');

        $child1 = $this->createProjectWithId('child-1');
        $child1->setName('Child 1');
        $child1->setPosition(1);
        $parent->addChild($child1);

        $child2 = $this->createProjectWithId('child-2');
        $child2->setName('Child 2');
        $child2->setPosition(0);
        $parent->addChild($child2);

        $result = $this->transformer->transformToTree([$parent, $child1, $child2]);

        $this->assertCount(2, $result[0]['children']);
        // Child 2 should come first due to lower position
        $this->assertEquals('child-2', $result[0]['children'][0]['id']);
        $this->assertEquals('child-1', $result[0]['children'][1]['id']);
    }

    public function testTransformToTreeIncludesTaskCounts(): void
    {
        $project = $this->createProjectWithId('project-1');
        $project->setName('Project 1');

        $taskCounts = [
            'project-1' => ['total' => 5, 'completed' => 3],
        ];

        $result = $this->transformer->transformToTree([$project], $taskCounts);

        $this->assertEquals(5, $result[0]['taskCount']);
        $this->assertEquals(3, $result[0]['completedTaskCount']);
        $this->assertEquals(2, $result[0]['pendingTaskCount']); // 5 - 3 = 2
    }

    public function testTransformToTreeHandlesDeepNesting(): void
    {
        $root = $this->createProjectWithId('root');
        $root->setName('Root');

        $level1 = $this->createProjectWithId('level1');
        $level1->setName('Level 1');
        $root->addChild($level1);

        $level2 = $this->createProjectWithId('level2');
        $level2->setName('Level 2');
        $level1->addChild($level2);

        $level3 = $this->createProjectWithId('level3');
        $level3->setName('Level 3');
        $level2->addChild($level3);

        $result = $this->transformer->transformToTree([$root, $level1, $level2, $level3]);

        $this->assertCount(1, $result);
        $this->assertEquals('root', $result[0]['id']);
        $this->assertEquals('level1', $result[0]['children'][0]['id']);
        $this->assertEquals('level2', $result[0]['children'][0]['children'][0]['id']);
        $this->assertEquals('level3', $result[0]['children'][0]['children'][0]['children'][0]['id']);
    }

    // ========================================
    // transformNode Tests
    // ========================================

    public function testTransformNodeReturnsCorrectStructure(): void
    {
        $project = $this->createProjectWithId('project-1');
        $project->setName('Project');
        $project->setDescription('Test description');
        $project->setColor('#FF0000');
        $project->setIcon('folder');
        $project->setPosition(5);
        $project->setIsArchived(false);
        $project->setShowChildrenTasks(true);

        $result = $this->transformer->transformNode($project);

        $this->assertEquals('project-1', $result['id']);
        $this->assertEquals('Project', $result['name']);
        $this->assertEquals('Test description', $result['description']);
        $this->assertEquals('#FF0000', $result['color']);
        $this->assertEquals('folder', $result['icon']);
        $this->assertEquals(5, $result['position']);
        $this->assertFalse($result['isArchived']);
        $this->assertTrue($result['showChildrenTasks']);
        $this->assertEquals(0, $result['depth']);
        $this->assertEquals(0, $result['taskCount']);
        $this->assertEquals(0, $result['completedTaskCount']);
        $this->assertEquals(0, $result['pendingTaskCount']);
        $this->assertEmpty($result['children']);
    }

    public function testTransformNodeWithChildren(): void
    {
        $project = $this->createProjectWithId('project-1');
        $project->setName('Project');

        $children = [
            ['id' => 'child-1', 'name' => 'Child 1', 'children' => []],
            ['id' => 'child-2', 'name' => 'Child 2', 'children' => []],
        ];

        $result = $this->transformer->transformNode($project, $children);

        $this->assertCount(2, $result['children']);
        $this->assertEquals('child-1', $result['children'][0]['id']);
        $this->assertEquals('child-2', $result['children'][1]['id']);
    }

    public function testTransformNodeWithTaskCounts(): void
    {
        $project = $this->createProjectWithId('project-1');
        $project->setName('Project');

        $taskCount = ['total' => 10, 'completed' => 7];

        $result = $this->transformer->transformNode($project, [], $taskCount);

        $this->assertEquals(10, $result['taskCount']);
        $this->assertEquals(7, $result['completedTaskCount']);
        $this->assertEquals(3, $result['pendingTaskCount']); // 10 - 7 = 3
    }
}
