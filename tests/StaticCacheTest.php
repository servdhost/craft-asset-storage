<?php

namespace servd\AssetStorage\Tests;

use craft\elements\Entry;
use craft\events\ElementEvent;
use craft\events\MoveElementEvent;
use craft\services\Structures;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use servd\AssetStorage\StaticCache\StaticCache;

class StaticCacheTest extends TestCase
{
    public function testStructureEventsAreRegisteredOnTheStructuresService(): void
    {
        $events = StaticCache::getElementUpdateEvents();

        $this->assertContains([Structures::class, Structures::EVENT_AFTER_INSERT_ELEMENT], $events);
        $this->assertContains([Structures::class, Structures::EVENT_AFTER_MOVE_ELEMENT], $events);
        $this->assertNotContains([\craft\base\Element::class, Structures::EVENT_AFTER_INSERT_ELEMENT], $events);
    }

    public function testStructureUpdateEventOfCraft59IsRegistered(): void
    {
        if (!defined(Structures::class . '::EVENT_AFTER_UPDATE_ELEMENT')) {
            $this->markTestSkipped('Craft before 5.9 has no Structures::EVENT_AFTER_UPDATE_ELEMENT.');
        }

        $this->assertContains(
            [Structures::class, Structures::EVENT_AFTER_UPDATE_ELEMENT],
            StaticCache::getElementUpdateEvents()
        );
    }

    public function testSavedStructureElementPurgesItsStructureTag(): void
    {
        $event = new ElementEvent(['element' => $this->entry(12, 3, 7), 'isNew' => false]);

        $this->assertSame(['sec3', 'el12', 'st7'], $this->tagsFor($event));
    }

    public function testSavedElementOutsideAStructureHasNoStructureTag(): void
    {
        $event = new ElementEvent(['element' => $this->entry(12, 3, null), 'isNew' => false]);

        $this->assertSame(['sec3', 'el12'], $this->tagsFor($event));
    }

    public function testMovedElementPurgesItsStructureTagOnce(): void
    {
        $event = new MoveElementEvent(['element' => $this->entry(12, 3, 7), 'structureId' => 7]);

        $this->assertSame(['sec3', 'el12', 'st7'], $this->tagsFor($event));
    }

    private function entry(int $id, ?int $sectionId, ?int $structureId): Entry
    {
        $entry = $this->getMockBuilder(Entry::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getIsDraft', 'getIsRevision', 'getPrimaryOwner', 'getOwner'])
            ->getMock();
        $entry->method('getId')->willReturn($id);
        $entry->method('getIsDraft')->willReturn(false);
        $entry->method('getIsRevision')->willReturn(false);
        $entry->method('getPrimaryOwner')->willReturn(null);
        $entry->method('getOwner')->willReturn(null);
        $entry->sectionId = $sectionId;
        $entry->structureId = $structureId;

        return $entry;
    }

    private function tagsFor($event): array
    {
        $class = new ReflectionClass(StaticCache::class);
        $staticCache = $class->newInstanceWithoutConstructor();
        $method = $class->getMethod('getTagsFromElementUpdateEvent');
        $method->setAccessible(true);

        return $method->invoke($staticCache, $event);
    }
}
