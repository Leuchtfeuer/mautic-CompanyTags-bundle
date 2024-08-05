<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\EventListener;

use Doctrine\DBAL\Connection;
use Mautic\LeadBundle\Model\CompanyReportData;
use Mautic\ReportBundle\Event\ReportBuilderEvent;
use Mautic\ReportBundle\Event\ReportGeneratorEvent;
use Mautic\ReportBundle\ReportEvents;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTagsRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ReportSubscriber implements EventSubscriberInterface
{
    public const CONTEXT_COMPANY_TAGS = 'company_tags';

    public const COMPANY_TAGS_XREF_PREFIX = 'ctx';

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

        $columns = $this->companyReportData->getCompanyData();
        unset($columns['companies_lead.is_primary'], $columns['companies_lead.date_added']);

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
        $filters = array_merge($columns, $tagFilter);

        // d($filters);
        $event->addTable(
            self::CONTEXT_COMPANY_TAGS,
            [
                'display_name' => 'mautic.companytag.report.companytags',
                'columns'      => $columns,
                'filters'      => $filters,
            ],
            'companies'
        );

        $event->addGraph(self::CONTEXT_COMPANY_TAGS, 'line', 'mautic.lead.graph.line.companies');
        $event->addGraph(self::CONTEXT_COMPANY_TAGS, 'pie', 'mautic.lead.graph.pie.companies.industry');
        $event->addGraph(self::CONTEXT_COMPANY_TAGS, 'pie', 'mautic.lead.table.pie.company.country');
        $event->addGraph(self::CONTEXT_COMPANY_TAGS, 'table', 'mautic.lead.company.table.top.cities');
    }

    public function onReportGenerate(ReportGeneratorEvent $event): void
    {
        if (!$event->checkContext([self::CONTEXT_COMPANY_TAGS])) {
            return;
        }

        $qb = $event->getQueryBuilder();
        $qb
            ->from(MAUTIC_TABLE_PREFIX.'companies', 'comp')
            ->leftJoin('comp', MAUTIC_TABLE_PREFIX.'companies_tags_xref', 'ctx', 'ctx.company_id = comp.id')
            ->leftJoin('ctx', MAUTIC_TABLE_PREFIX.'company_tags', 'ct', 'ct.id = ctx.tag_id');

        $tagFilter = self::COMPANY_TAGS_XREF_PREFIX.'.tag_id';
        if ($event->hasFilter($tagFilter)) {
            $filters  = $event->getReport()->getFilters();

            foreach ($filters as $filter) {
                if ($filter['column'] === $tagFilter) {
                    if (in_array($filter['condition'], ['in', 'notIn']) && !empty($filter['value'])) {
                    }
                }
                if (in_array($filter['condition'], ['empty', 'notIn']) && !empty($filter['value'])) {
                    $filters   = $event->getReport()->getFilters();
                    $filters[] = [
                        'column'    => 'ctx.company_id',
                        'value'     => '',
                        'condition' => 'empty',
                        'dynamic'   => null,
                        'glue'      => 'or',
                    ];
                    $event->getReport()->setFilters($filters);

                    $tagSubQuery = $this->db->createQueryBuilder();
                    $tagSubQuery->select('DISTINCT id')
                        ->from(MAUTIC_TABLE_PREFIX.'companies', 'c')
                        ->leftJoin('c', MAUTIC_TABLE_PREFIX.'companies_tags_xref', 'ctx', 'ctx.company_id = c.id');
                    $tagSubQuery->andWhere($tagSubQuery->expr()->in('ctx.tag_id', ':filter_value'));

                    if (in_array($filter['condition'], ['in', 'notEmpty'])) {
                        $qb->andWhere($qb->expr()->in('comp.id', $tagSubQuery->getSQL()))
                            ->setParameter('filter_value', $filter['value']);
                    } elseif (in_array($filter['condition'], ['notIn', 'empty'])) {
                        $qb->andWhere($qb->expr()->notIn('comp.id', $tagSubQuery->getSQL()))
                            ->setParameter('filter_value', $filter['value']);
                    }
                }
            }
        }
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
}
