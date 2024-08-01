<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\EventListener;


use Mautic\LeadBundle\Model\CompanyReportData;
use Mautic\ReportBundle\Event\ReportBuilderEvent;
use Mautic\ReportBundle\Event\ReportDataEvent;
use Mautic\ReportBundle\Event\ReportGeneratorEvent;
use Mautic\ReportBundle\ReportEvents;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTagsRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Doctrine\DBAL\Connection;

class ReportSubscriber implements EventSubscriberInterface
{

    //const CONTEXT_COMPANY_TAGS = 'companies';
    const CONTEXT_COMPANY_TAGS = 'company_tags';

    const COMPANY_TAGS_XREF_PREFIX = 'ctx';
    const COMPANY_TAGS_PREFIX = 'ct';


    public function __construct(
        private CompanyReportData     $companyReportData,
        private CompanyTagsRepository $companyTagsRepository,
        private Connection $db,
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ReportEvents::REPORT_ON_BUILD => ['onReportBuilder', 0],
            ReportEvents::REPORT_ON_GENERATE => ['onReportGenerate', 0],
            //ReportEvents::REPORT_ON_DISPLAY  => ['onReportDisplay', 0],
        ];
    }


    public function onReportBuilder(ReportBuilderEvent $event): void
    {
        if (!$event->checkContext([self::CONTEXT_COMPANY_TAGS])) {
            return;
        }

        // message queue

        $newColumns = [
            self::COMPANY_TAGS_PREFIX . '.'.'tag' => [
                'label' => 'mautic.companytag.report.companytags.tag',
                'type' => 'string',
            ],
        ];

        $columns = $this->companyReportData->getCompanyData();


        //getTagsForFilters
        $tags = $this->companyTagsRepository->getAllTagObjects();
        $tagList = [];
        foreach ($tags as $tag) {
            $tagList[$tag->getId()] = $tag->getTag();
        }

        $tagFilter = [self::COMPANY_TAGS_XREF_PREFIX.'.'.'tag_id' => [
            "alias" => "companytags",
            "label" => "Company Tags",
            "type" => "select",
            "list" => $tagList,
            "operators" => [
                "in" => "mautic.core.operator.in",
                "notIn" => "mautic.core.operator.notin"
            ]
        ]
        ];
        $filters = array_merge($columns, $tagFilter);

        //d($filters);
        $event->addTable(
            self::CONTEXT_COMPANY_TAGS,
            [
                'display_name' => 'mautic.companytag.report.companytags',
                'columns' => $columns,
                'filters' => $filters,
            ],
            'companies'
        );

        //dd($event);


        //dump($event);
        $event->addGraph(self::CONTEXT_COMPANY_TAGS, 'line', 'mautic.lead.graph.line.companies');
        $event->addGraph(self::CONTEXT_COMPANY_TAGS, 'pie', 'mautic.lead.graph.pie.companies.industry');
        $event->addGraph(self::CONTEXT_COMPANY_TAGS, 'pie', 'mautic.lead.table.pie.company.country');
        $event->addGraph(self::CONTEXT_COMPANY_TAGS, 'table', 'mautic.lead.company.table.top.cities');
    }


    public function onReportGenerate(ReportGeneratorEvent $event)
    {
        if (!$event->checkContext([self::CONTEXT_COMPANY_TAGS])) {
            return;
        }

        $qb = $event->getQueryBuilder();
        $qb
            ->from(MAUTIC_TABLE_PREFIX . 'companies', 'c')
            ->leftJoin('c', MAUTIC_TABLE_PREFIX . 'companies_tags_xref', 'ctx', 'ctx.company_id = c.id')
            ->leftJoin('ctx', MAUTIC_TABLE_PREFIX . 'companies', 'comp', 'comp.id = ctx.company_id')
            ->leftJoin('ctx', MAUTIC_TABLE_PREFIX . 'company_tags', 'ct', 'ct.id = ctx.tag_id');

        $tagFilter = self::COMPANY_TAGS_XREF_PREFIX . '.' . 'tag_id';
        if ($event->hasFilter($tagFilter)) {
            $tagSubQuery = $this->db->createQueryBuilder();
            //$tagSubQuery->select('DISTINCT company_id')
                //->from(MAUTIC_TABLE_PREFIX.'companies_tags_xref', 'ctx');

            $tagSubQuery->select('DISTINCT id')
                ->from(MAUTIC_TABLE_PREFIX.'companies', 'c');
            $abc = ['1,2'];

            $report = $event->getReport();
            $filters = $report->getFilters();

            foreach ($filters as $filter) {
                if ($filter["column"] === $tagFilter) {
                    if (in_array($filter['condition'], ['in', 'notIn']) && !empty($filter['value'])) {
                        $tagSubQuery->andWhere($tagSubQuery->expr()->in('ctx.tag_id', ':filter_value'));

                        if (in_array($filter['condition'], ['in', 'notEmpty'])) {

                            $qb->andWhere($qb->expr()->in('company_id', $tagSubQuery->getSQL()))
                                ->setParameter('filter_value', $abc);
                        } elseif (in_array($filter['condition'], ['notIn', 'empty'])) {
                            $qb->andWhere($qb->expr()->notIn('company_id', $tagSubQuery->getSQL()))
                                ->setParameter('filter_value', $abc);
                        }
                    }
                }
            }
        }
        //$event->applyDateFilters($qb, 'date_added', 'comp');
    }
        //$filters = $event->getFilterValues();
        //Check if filter is the same as needed

        /*
        if (in_array($filter['condition'], ['in', 'notIn']) && !empty($filter['value'])) {
            $tagSubQuery->where($tagSubQuery->expr()->in('ltx.tag_id', $filter['value']));
            //Change tagSubQuery to qb
            //Adjust tables and columns
        }
        if (in_array($filter['condition'], ['in', 'notEmpty'])) {
            $tagSubQuery->expr()->in('c.id', $qb->getSQL());
        } elseif (in_array($filter['condition'], ['notIn', 'empty'])) {
            $tagSubQuery->expr()->notIn('c.id', $qb->getSQL());
        }
    */

/*
        public function onReportDisplay(ReportDataEvent $event): void
        {
            //get array columns
            $data = $event->getData();
            $report = $event->getReport();
            $filters = $report->getFilters();
            //Hier checken, ob Comp_ID und Tag_Filter
            //Hier erst checken ob tag filter existiert
            if (in_array('ctx.tag_id', array_column($filters, 'column'))) {

            }
            $columns = array_column($filters, 'column');
            //dd($data, $filters, $report, in_array('ctx.tag_id', $columns));

            if ($event->checkContext([
                self::CONTEXT_COMPANY_TAGS,
            ])) {

                if (isset($data[0]['channel']) || isset($data[0]['channel_action']) || (isset($data[0]['activity_count']) && isset($data[0]['attribution']))) {
                    foreach ($data as &$row) {
                        if (isset($row['channel'])) {
                            $row['channel'] = $this->channels[$row['channel']];
                        }

                        if (isset($row['channel_action'])) {
                            $row['channel_action'] = $this->channelActions[$row['channel_action']];
                        }

                        if (isset($row['activity_count']) && isset($row['attribution'])) {
                            $row['attribution'] = round($row['attribution'] / $row['activity_count'], 2);
                        }

                        if (isset($row['attribution'])) {
                            $row['attribution'] = number_format($row['attribution'], 2);
                        }

                        //unset($row);
                    }
                }
            }

            //$event->setData($data);
            //unset($data);
        }
*/

}