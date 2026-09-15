<?php

declare(strict_types=1);
/*
 * Probe 6 fixture: a tandem repeat — one method written ten times.
 *
 * Modelled on the shape that produced the scale cascade in the wild (phpunit's
 * MetadataTest.php, eighty-three parameterised test methods): every unit is the
 * same skeleton with one name changed, so the file matches itself at every
 * multiple of its period at once — one method against the next, two against the
 * two after them, five against the five after them.
 *
 * The unit is deliberately well above the 50-token minimum these probes run at,
 * because a period below --min-tokens is one the engine must NOT aggregate away:
 * there would be no reportable unit left to hand the evidence to.
 */
final class TandemRepeat
{
    public function testCanBeAfter(): void
    {
        $metadata = Metadata::after('Example', self::DEFAULT_TARGET);

        $this->assertTrue($metadata->isAfter());
        $this->assertFalse($metadata->isSomethingEntirelyDifferent());
        $this->assertSame('Example', $metadata->className());
        $this->assertSame(self::DEFAULT_TARGET, $metadata->target());
        $this->assertNotNull($metadata->declaringClass(), 'declaring class must be set');
        $this->assertCount(1, $metadata->collected());
        $this->assertArrayHasKey('Example', $metadata->indexedByName());
        $this->assertStringContainsString('Example', $metadata->describe());
        $this->assertGreaterThan(0, $metadata->priority());
        $this->assertLessThanOrEqual(10, $metadata->priority());
    }

    public function testCanBeAfterClass(): void
    {
        $metadata = Metadata::afterClass('Example', self::DEFAULT_TARGET);

        $this->assertTrue($metadata->isAfterClass());
        $this->assertFalse($metadata->isSomethingEntirelyDifferent());
        $this->assertSame('Example', $metadata->className());
        $this->assertSame(self::DEFAULT_TARGET, $metadata->target());
        $this->assertNotNull($metadata->declaringClass(), 'declaring class must be set');
        $this->assertCount(1, $metadata->collected());
        $this->assertArrayHasKey('Example', $metadata->indexedByName());
        $this->assertStringContainsString('Example', $metadata->describe());
        $this->assertGreaterThan(0, $metadata->priority());
        $this->assertLessThanOrEqual(10, $metadata->priority());
    }

    public function testCanBeBackupGlobals(): void
    {
        $metadata = Metadata::backupGlobals('Example', self::DEFAULT_TARGET);

        $this->assertTrue($metadata->isBackupGlobals());
        $this->assertFalse($metadata->isSomethingEntirelyDifferent());
        $this->assertSame('Example', $metadata->className());
        $this->assertSame(self::DEFAULT_TARGET, $metadata->target());
        $this->assertNotNull($metadata->declaringClass(), 'declaring class must be set');
        $this->assertCount(1, $metadata->collected());
        $this->assertArrayHasKey('Example', $metadata->indexedByName());
        $this->assertStringContainsString('Example', $metadata->describe());
        $this->assertGreaterThan(0, $metadata->priority());
        $this->assertLessThanOrEqual(10, $metadata->priority());
    }

    public function testCanBeBackupStaticProperties(): void
    {
        $metadata = Metadata::backupStaticProperties('Example', self::DEFAULT_TARGET);

        $this->assertTrue($metadata->isBackupStaticProperties());
        $this->assertFalse($metadata->isSomethingEntirelyDifferent());
        $this->assertSame('Example', $metadata->className());
        $this->assertSame(self::DEFAULT_TARGET, $metadata->target());
        $this->assertNotNull($metadata->declaringClass(), 'declaring class must be set');
        $this->assertCount(1, $metadata->collected());
        $this->assertArrayHasKey('Example', $metadata->indexedByName());
        $this->assertStringContainsString('Example', $metadata->describe());
        $this->assertGreaterThan(0, $metadata->priority());
        $this->assertLessThanOrEqual(10, $metadata->priority());
    }

    public function testCanBeBeforeClass(): void
    {
        $metadata = Metadata::beforeClass('Example', self::DEFAULT_TARGET);

        $this->assertTrue($metadata->isBeforeClass());
        $this->assertFalse($metadata->isSomethingEntirelyDifferent());
        $this->assertSame('Example', $metadata->className());
        $this->assertSame(self::DEFAULT_TARGET, $metadata->target());
        $this->assertNotNull($metadata->declaringClass(), 'declaring class must be set');
        $this->assertCount(1, $metadata->collected());
        $this->assertArrayHasKey('Example', $metadata->indexedByName());
        $this->assertStringContainsString('Example', $metadata->describe());
        $this->assertGreaterThan(0, $metadata->priority());
        $this->assertLessThanOrEqual(10, $metadata->priority());
    }

    public function testCanBeBefore(): void
    {
        $metadata = Metadata::before('Example', self::DEFAULT_TARGET);

        $this->assertTrue($metadata->isBefore());
        $this->assertFalse($metadata->isSomethingEntirelyDifferent());
        $this->assertSame('Example', $metadata->className());
        $this->assertSame(self::DEFAULT_TARGET, $metadata->target());
        $this->assertNotNull($metadata->declaringClass(), 'declaring class must be set');
        $this->assertCount(1, $metadata->collected());
        $this->assertArrayHasKey('Example', $metadata->indexedByName());
        $this->assertStringContainsString('Example', $metadata->describe());
        $this->assertGreaterThan(0, $metadata->priority());
        $this->assertLessThanOrEqual(10, $metadata->priority());
    }

    public function testCanBeCoversClass(): void
    {
        $metadata = Metadata::coversClass('Example', self::DEFAULT_TARGET);

        $this->assertTrue($metadata->isCoversClass());
        $this->assertFalse($metadata->isSomethingEntirelyDifferent());
        $this->assertSame('Example', $metadata->className());
        $this->assertSame(self::DEFAULT_TARGET, $metadata->target());
        $this->assertNotNull($metadata->declaringClass(), 'declaring class must be set');
        $this->assertCount(1, $metadata->collected());
        $this->assertArrayHasKey('Example', $metadata->indexedByName());
        $this->assertStringContainsString('Example', $metadata->describe());
        $this->assertGreaterThan(0, $metadata->priority());
        $this->assertLessThanOrEqual(10, $metadata->priority());
    }

    public function testCanBeCoversNamespace(): void
    {
        $metadata = Metadata::coversNamespace('Example', self::DEFAULT_TARGET);

        $this->assertTrue($metadata->isCoversNamespace());
        $this->assertFalse($metadata->isSomethingEntirelyDifferent());
        $this->assertSame('Example', $metadata->className());
        $this->assertSame(self::DEFAULT_TARGET, $metadata->target());
        $this->assertNotNull($metadata->declaringClass(), 'declaring class must be set');
        $this->assertCount(1, $metadata->collected());
        $this->assertArrayHasKey('Example', $metadata->indexedByName());
        $this->assertStringContainsString('Example', $metadata->describe());
        $this->assertGreaterThan(0, $metadata->priority());
        $this->assertLessThanOrEqual(10, $metadata->priority());
    }

    public function testCanBeDataProvider(): void
    {
        $metadata = Metadata::dataProvider('Example', self::DEFAULT_TARGET);

        $this->assertTrue($metadata->isDataProvider());
        $this->assertFalse($metadata->isSomethingEntirelyDifferent());
        $this->assertSame('Example', $metadata->className());
        $this->assertSame(self::DEFAULT_TARGET, $metadata->target());
        $this->assertNotNull($metadata->declaringClass(), 'declaring class must be set');
        $this->assertCount(1, $metadata->collected());
        $this->assertArrayHasKey('Example', $metadata->indexedByName());
        $this->assertStringContainsString('Example', $metadata->describe());
        $this->assertGreaterThan(0, $metadata->priority());
        $this->assertLessThanOrEqual(10, $metadata->priority());
    }

    public function testCanBeDependsOnClass(): void
    {
        $metadata = Metadata::dependsOnClass('Example', self::DEFAULT_TARGET);

        $this->assertTrue($metadata->isDependsOnClass());
        $this->assertFalse($metadata->isSomethingEntirelyDifferent());
        $this->assertSame('Example', $metadata->className());
        $this->assertSame(self::DEFAULT_TARGET, $metadata->target());
        $this->assertNotNull($metadata->declaringClass(), 'declaring class must be set');
        $this->assertCount(1, $metadata->collected());
        $this->assertArrayHasKey('Example', $metadata->indexedByName());
        $this->assertStringContainsString('Example', $metadata->describe());
        $this->assertGreaterThan(0, $metadata->priority());
        $this->assertLessThanOrEqual(10, $metadata->priority());
    }
}
