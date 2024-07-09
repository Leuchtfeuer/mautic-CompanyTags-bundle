<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\EventListener;


use Mautic\LeadBundle\Model\CompanyReportData;
use Mautic\ReportBundle\Event\ReportBuilderEvent;
use Mautic\ReportBundle\Event\ReportDataEvent;
use Mautic\ReportBundle\Event\ReportGeneratorEvent;
use Mautic\ReportBundle\ReportEvents;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTagsRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use function Aws\filter;

class ReportSubscriber implements EventSubscriberInterface
{

    //const CONTEXT_COMPANY_TAGS = 'companies';
    const CONTEXT_COMPANY_TAGS = 'company_tags';

    const COMPANY_TAGS_XREF_PREFIX = 'ctx';
    const COMPANY_TAGS_PREFIX = 'ct';


    public function __construct(
        private CompanyReportData     $companyReportData,
        private CompanyTagsRepository $companyTagsRepository,
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
            $tagList[$tag->getId()] = (string) $tag->getTag();
        }

        $tagFilter = [self::COMPANY_TAGS_XREF_PREFIX.'.'.'tag_id' => [
            "alias" => "companytags",
            "label" => "Company Tags",
            "type" => "select",
            "list" => $tagList,
            "operators" => [
                "eq" => "mautic.core.operator.equals"
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


    public function onReportGenerate(ReportGeneratorEvent $event){

        if (!$event->checkContext([self::CONTEXT_COMPANY_TAGS])) {
            return;
        }

        $qb      = $event->getQueryBuilder();


        $qb->from(MAUTIC_TABLE_PREFIX.'companies', 'c');

        if ($event->hasFilter(self::COMPANY_TAGS_XREF_PREFIX .'.'. 'tag_id')) {
            $qb
                ->leftJoin('c', MAUTIC_TABLE_PREFIX.'companies_tags_xref', 'ctx', 'ctx.company_id = c.id')
                ->leftJoin('ctx', MAUTIC_TABLE_PREFIX.'companies', 'comp', 'comp.id = ctx.company_id')
                ->leftJoin('ctx', MAUTIC_TABLE_PREFIX.'company_tags', 'ct', 'ct.id = ctx.tag_id');
        }

        $event->applyDateFilters($qb, 'date_added', 'comp');

    }

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