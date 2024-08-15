<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\EventListener;


use Mautic\LeadBundle\Model\CompanyReportData;
use Mautic\ReportBundle\Event\ReportBuilderEvent;
use Mautic\ReportBundle\Event\ReportGeneratorEvent;
use Mautic\ReportBundle\ReportEvents;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTagsRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ReportSubscriber implements EventSubscriberInterface
{

    const CONTEXT_COMPANY_TAGS = 'company_tags';

    const COMPANY_TAGS_XREF_PREFIX = 'ctx';



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
        ];
    }


    public function onReportBuilder(ReportBuilderEvent $event): void
    {
        if (!$event->checkContext([self::CONTEXT_COMPANY_TAGS])) {
            return;
        }

        $columns = $this->companyReportData->getCompanyData();

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

        $event->addTable(
            self::CONTEXT_COMPANY_TAGS,
            [
                'display_name' => 'mautic.companytag.report.companytags',
                'columns' => $columns,
                'filters' => $filters,
            ],
            'companies'
        );

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

    }

}