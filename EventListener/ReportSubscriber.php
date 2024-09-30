<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\EventListener;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\LeadBundle\Model\CompanyReportData;
use Mautic\ReportBundle\Event\ReportBuilderEvent;
use Mautic\ReportBundle\Event\ReportGeneratorEvent;
use Mautic\ReportBundle\ReportEvents;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTagsRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ReportSubscriber implements EventSubscriberInterface
{
    public const CONTEXT_COMPANY_TAGS     = 'company_tags';
    public const COMPANY_TAGS_XREF_PREFIX = 'ctx';
    public const COMPANY_TABLE            = 'companies';
    public const COMPANIES_PREFIX         = 'comp';
    public const COMPANY_TAGS_XREF_TABLE  = 'companies_companies_tags_xref';

    public function __construct(
        private CompanyReportData $companyReportData,
        private CompanyTagsRepository $companyTagsRepository,
        private Connection $db,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ReportEvents::REPORT_ON_BUILD    => ['onReportBuilder', 0],
            ReportEvents::REPORT_ON_GENERATE => ['onReportGenerate', 0],
        ];
    }

    public function onReportBuilder(ReportBuilderEvent $event): void
    {
        if (!$event->checkContext([self::CONTEXT_COMPANY_TAGS])) {
            return;
        }

        $keys = [
            'comp.id',
            'comp.companyaddress1',
            'comp.companyaddress2',
            'comp.companyemail',
            'comp.companyphone',
            'comp.companycity',
            'comp.companystate',
            'comp.companyzipcode',
            'comp.companycountry',
            'comp.companyname',
            'comp.companywebsite',
            'comp.companynumber_of_employees',
            'comp.companyfax',
            'comp.companyannual_revenue',
            'comp.companyindustry',
            'comp.companydescription',
        ];
        $columns         = $this->companyReportData->getCompanyData();
        $filteredColumns = array_intersect_key($columns, array_flip($keys));

        $tagList = $this->getFilterTags();

        $tagFilter = [self::COMPANY_TAGS_XREF_PREFIX.'.tag_id' => [
            'alias'     => 'companytags',
            'label'     => 'mautic.companytag.report.companytags',
            'type'      => 'select',
            'list'      => $tagList,
            'operators' => [
                'in'       => 'mautic.core.operator.in',
                'notIn'    => 'mautic.core.operator.notin',
                'empty'    => 'mautic.core.operator.isempty',
                'notEmpty' => 'mautic.core.operator.isnotempty',
            ],
        ],
        ];
        $filters = array_merge($filteredColumns, $tagFilter);

        $event->addTable(
            self::CONTEXT_COMPANY_TAGS,
            [
                'display_name' => 'mautic.companytag.report.companytags',
                'columns'      => $filteredColumns,
                'filters'      => $filters,
            ],
            self::COMPANY_TABLE
        );
    }

    /**
     * @return array<string, string>
     */
    public function getFilterTags(): array
    {
        $tags    = $this->companyTagsRepository->getAllTagObjects();
        $tagList = [];
        foreach ($tags as $tag) {
            $tagList[(string) $tag->getId()] = (string) $tag->getTag();
        }

        return $tagList;
    }

    public function onReportGenerate(ReportGeneratorEvent $event): void
    {
        if (!$event->checkContext([self::CONTEXT_COMPANY_TAGS])) {
            return;
        }

        $qb       = $event->getQueryBuilder();
        $filters  = $event->getReport()->getFilters();
        $options  = $event->getOptions()['columns'];
        $orGroups = [];
        $andGroup = [];

        $qb
            ->from(MAUTIC_TABLE_PREFIX.self::COMPANY_TABLE, self::COMPANIES_PREFIX);

        $expr     = $qb->expr();

        if (count($filters)) {
            foreach ($filters as $i => $filter) {
                $exprFunction = $filter['expr'] ?? $filter['condition'];
                $paramName    = sprintf('i%dc%s', $i, InputHelper::alphanum($filter['column']));

                if (array_key_exists('glue', $filter) && 'or' === $filter['glue']) {
                    if ($andGroup) {
                        $orGroups[] = CompositeExpression::and(...$andGroup);
                        $andGroup   = [];
                    }
                }

                $companyTagCondition = $this->getCompanyTagCondition($filter);
                if ($companyTagCondition) {
                    $andGroup[] = $companyTagCondition;
                    continue;
                }
                switch ($exprFunction) {
                    case 'notEmpty':
                        $andGroup[] = $expr->isNotNull($filter['column']);
                        if ($this->doesColumnSupportEmptyValue($filter, $options)) {
                            $andGroup[] = $expr->neq($filter['column'], $expr->literal(''));
                        }
                        break;
                    case 'empty':
                        $expression = $qb->expr()->or(
                            $qb->expr()->isNull($filter['column'])
                        );
                        if ($this->doesColumnSupportEmptyValue($filter, $options)) {
                            $expression = $expression->with(
                                $qb->expr()->eq($filter['column'], $expr->literal(''))
                            );
                        }

                        $andGroup[] = $expression;
                        break;
                    case 'neq':
                        $columnValue = ":$paramName";
                        $expression  = $qb->expr()->or(
                            $qb->expr()->isNull($filter['column']),
                            $qb->expr()->$exprFunction($filter['column'], $columnValue)
                        );
                        $qb->setParameter($paramName, $filter['value']);
                        $andGroup[] = $expression;
                        break;
                    default:
                        if ('' == trim($filter['value'])) {
                            // Ignore empty
                            break;
                        }

                        $columnValue = ":$paramName";
                        $type        = $options[$filter['column']]['type'];
                        if (isset($options[$filter['column']]['formula'])) {
                            $filter['column'] = $options[$filter['column']]['formula'];
                        }

                        switch ($type) {
                            case 'bool':
                            case 'boolean':
                                if ((int) $filter['value'] > 1) {
                                    // Ignore the "reset" value of "2"
                                    break 2;
                                }

                                $qb->setParameter($paramName, $filter['value'], 'boolean');
                                break;

                            case 'float':
                                $columnValue = (float) $filter['value'];
                                break;

                            case 'int':
                            case 'integer':
                                $columnValue = (int) $filter['value'];
                                break;

                            case 'string':
                            case 'email':
                            case 'url':
                                switch ($exprFunction) {
                                    case 'like':
                                    case 'notLike':
                                        $filter['value'] = !str_contains($filter['value'], '%') ? '%'.$filter['value'].'%' : $filter['value'];
                                        break;
                                    case 'startsWith':
                                        $exprFunction    = 'like';
                                        $filter['value'] = $filter['value'].'%';
                                        break;
                                    case 'endsWith':
                                        $exprFunction    = 'like';
                                        $filter['value'] = '%'.$filter['value'];
                                        break;
                                    case 'contains':
                                        $exprFunction    = 'like';
                                        $filter['value'] = '%'.$filter['value'].'%';
                                        break;
                                }

                                $qb->setParameter($paramName, $filter['value']);
                                break;

                            default:
                                $qb->setParameter($paramName, $filter['value']);
                        }
                        $andGroup[] = $expr->{$exprFunction}($filter['column'], $columnValue);
                }
            }
        }

        if ($orGroups) {
            // Add the remaining $andGroup to the rest of the $orGroups if exists so we don't miss it.
            $orGroups[] = CompositeExpression::and(...$andGroup);
            $qb->andWhere(CompositeExpression::or(...$orGroups));
        } elseif ($andGroup) {
            $qb->andWhere(CompositeExpression::and(...$andGroup));
        }

        $event->getReport()->setFilters([]);
    }

    /**
     * @param array<string, mixed> $filter
     */
    public function getCompanyTagCondition(array $filter): ?string
    {
        if (self::COMPANY_TAGS_XREF_PREFIX.'.tag_id' !== $filter['column']) {
            return null;
        }

        $tagSubQuery = $this->db->createQueryBuilder();
        $tagSubQuery->select('DISTINCT '.self::COMPANY_TAGS_XREF_PREFIX.'.company_id')
            ->from(MAUTIC_TABLE_PREFIX.self::COMPANY_TAGS_XREF_TABLE, self::COMPANY_TAGS_XREF_PREFIX);

        if (in_array($filter['condition'], ['in', 'notIn']) && !empty($filter['value'])) {
            $tagSubQuery->andWhere($tagSubQuery->expr()->in(self::COMPANY_TAGS_XREF_PREFIX.'.tag_id', $filter['value']));
        }

        if (in_array($filter['condition'], ['in', 'notEmpty'])) {
            return $tagSubQuery->expr()->in(self::COMPANIES_PREFIX.'.id', $tagSubQuery->getSQL());
        } elseif (in_array($filter['condition'], ['notIn', 'empty'])) {
            return $tagSubQuery->expr()->notIn(self::COMPANIES_PREFIX.'.id', $tagSubQuery->getSQL());
        }

        return null;
    }

    /**
     * @param mixed[] $filter
     * @param mixed[] $filterDefinitions
     */
    private function doesColumnSupportEmptyValue(array $filter, array $filterDefinitions): bool
    {
        $type = $filterDefinitions[$filter['column']]['type'] ?? null;

        return !in_array($type, ['date', 'datetime'], true);
    }
}
