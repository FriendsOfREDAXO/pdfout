<?php

declare(strict_types=1);

namespace Metadata;

/**
 * Interface for advanced Metadata Factory implementations.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 * @author Jordan Stout <j@jrdn.org>
 */
interface AdvancedMetadataFactoryInterface extends MetadataFactoryInterface
{
    /**
     * Gets all the possible classes.
     *
     * @return string[]
     *
     * @throws \RuntimeException When driver does not an advanced driver.
     */
    public function getAllClassNames(): array;
}
