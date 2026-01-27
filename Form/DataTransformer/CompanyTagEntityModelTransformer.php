<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Form\DataTransformer;

use Doctrine\ORM\PersistentCollection;
use Mautic\LeadBundle\Form\DataTransformer\TagEntityModelTransformer;

/**
 * Extends TagEntityModelTransformer to return IDs instead of tag names.
 * This ensures that Company Tag events store IDs in their properties,
 * making them resilient to tag name changes.
 */
class CompanyTagEntityModelTransformer extends TagEntityModelTransformer
{
    /**
     * Transforms entities to IDs (instead of tag names).
     *
     * @param object|array<int, object>|PersistentCollection<int, object>|null $entity
     *
     * @return int|array<int, int>|null
     */
    public function reverseTransform($entity)
    {
        $isArray = is_array($entity) || $entity instanceof PersistentCollection;

        if (!$isArray) {
            if (is_null($entity) || !is_object($entity)) {
                return null;
            }

            // Return ID instead of tag name
            if (!method_exists($entity, 'getId')) {
                return null;
            }

            return $entity->getId();
        }

        $return = [];
        foreach ($entity as $e) {
            if (!is_object($e) || !method_exists($e, 'getId')) {
                continue;
            }
            // Return ID instead of tag name
            $return[] = $e->getId();
        }

        return $return;
    }
}
