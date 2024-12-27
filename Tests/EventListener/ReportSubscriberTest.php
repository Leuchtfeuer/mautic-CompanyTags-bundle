<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Tests\EventListener;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Mautic\ChannelBundle\Helper\ChannelListHelper;
use Mautic\LeadBundle\Model\CompanyReportData;
use Mautic\LeadBundle\Segment\Query\Expression\ExpressionBuilder;
use Mautic\ReportBundle\Event\ReportBuilderEvent;
use Mautic\ReportBundle\Helper\ReportHelper;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTags;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTagsRepository;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\EventListener\ReportSubscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ReportSubscriberTest extends TestCase
{
    private ReportSubscriber $reportSubscriber;
    /** @var MockObject&ReportBuilderEvent */
    private MockObject $reportBuilderEventMock;
    /** @var MockObject&CompanyReportData */
    private MockObject $companyReportDataMock;
    /** @var MockObject&CompanyTagsRepository */
    private MockObject $companyTagsRepositoryMock;
    /** @var MockObject&Connection */
    private MockObject $dbMock;
    /** @var MockObject&TranslatorInterface */
    private MockObject $translatorMock;
    /** @var MockObject&ChannelListHelper */
    private MockObject $channelListHelperMock;
    private ReportHelper $reportHelper;
    /** @var MockObject&EventDispatcherInterface */
    private MockObject $eventDispatcherMock;
    /** @var MockObject&QueryBuilder */
    private MockObject $queryBuilderMock;
    /** @var MockObject&ExpressionBuilder */
    private MockObject $exprMock;
    /** @var array<string, array<string, string>> */
    private array $columns;

    protected function setUp(): void
    {
        $this->columns = [
            'comp.id' => [
                'alias' => 'comp_id',
                'label' => 'mautic.lead.report.company.company_id',
                'type'  => 'int',
                'link'  => 'mautic_company_action',
            ],
            'companies_lead.is_primary' => [
                'label' => 'mautic.lead.report.company.is_primary',
                'type'  => 'bool',
            ],
            'companies_lead.date_added' => [
                'label' => 'mautic.lead.report.company.date_added',
                'type'  => 'datetime',
            ],
            'comp.companyaddress1' => [
                'label' => 'Company Address 1',
                'type'  => 'string',
            ],
            'comp.companyaddress2' => [
                'label' => 'Company Address 2',
                'type'  => 'string',
            ],
            'comp.companyemail' => [
                'label' => 'Company Company Email',
                'type'  => 'email',
            ],
            'comp.companyphone' => [
                'label' => 'Company Phone',
                'type'  => 'string',
            ],
            'comp.companycity' => [
                'label' => 'Company City',
                'type'  => 'string',
            ],
            'comp.companystate' => [
                'label' => 'Company State',
                'type'  => 'string',
            ],
            'comp.companyzipcode' => [
                'label' => 'Company Zip Code',
                'type'  => 'string',
            ],
            'comp.companycountry' => [
                'label' => 'Company Country',
                'type'  => 'string',
            ],
            'comp.companyname' => [
                'label' => 'Company Company Name',
                'type'  => 'string',
            ],
            'comp.companywebsite' => [
                'label' => 'Company Website',
                'type'  => 'url',
            ],
            'comp.companynumber_of_employees' => [
                'label' => 'Company Number of Employees',
                'type'  => 'float',
            ],
            'comp.companyfax' => [
                'label' => 'Company Fax',
                'type'  => 'string',
            ],
            'comp.companyannual_revenue' => [
                'label' => 'Company Annual Revenue',
                'type'  => 'float',
            ],
            'comp.companyindustry' => [
                'label' => 'Company Industry',
                'type'  => 'string',
            ],
            'comp.companydescription' => [
                'label' => 'Company Description',
                'type'  => 'string',
            ],
        ];

        $this->companyReportDataMock        = $this->createMock(CompanyReportData::class);
        $this->companyTagsRepositoryMock    = $this->createMock(CompanyTagsRepository::class);
        $this->dbMock                       = $this->createMock(Connection::class);
        $this->translatorMock               = $this->createMock(TranslatorInterface::class);
        $this->channelListHelperMock        = $this->createMock(ChannelListHelper::class);
        $this->eventDispatcherMock          = $this->createMock(EventDispatcherInterface::class);
        $this->queryBuilderMock             = $this->createMock(QueryBuilder::class);
        $this->queryBuilderMock->method('select')->willReturnSelf();
        $this->queryBuilderMock->method('from')->willReturnSelf();
        $this->queryBuilderMock->method('andWhere')->willReturnSelf();
        $this->exprMock = $this->createMock(ExpressionBuilder::class);
        $this->queryBuilderMock->method('expr')->willReturn($this->exprMock);

        $this->reportHelper = new ReportHelper($this->eventDispatcherMock);

        $this->reportBuilderEventMock = $this->getMockBuilder(ReportBuilderEvent::class)
            ->setConstructorArgs([
                $this->translatorMock,
                $this->channelListHelperMock,
                'company_tags', // context
                [],
                $this->reportHelper,
                null,
            ])
            ->onlyMethods(['checkContext'])
            ->getMock();

        $this->dbMock->method('createQueryBuilder')->willReturn($this->queryBuilderMock);

        $this->reportSubscriber = new ReportSubscriber(
            $this->companyReportDataMock,
            $this->companyTagsRepositoryMock,
            $this->dbMock
        );
    }

    /**
     * @return array<int, CompanyTags>
     */
    private function createFakeCompanyTags(): array
    {
        $tags = [];
        for ($i = 1; $i <= 4; ++$i) {
            $tag = $this->createMock(CompanyTags::class);
            $tag->method('getId')->willReturn($i);
            $tag->method('getTag')->willReturn(chr(96 + $i)); // 'a', 'b', 'c', 'd'
            $tags[$i] = $tag;
        }

        return $tags;
    }

    public function testOnReportBuilderAddsCompanyTagsToReportWithCorrectColumnsAndFilters(): void
    {
        $this->reportBuilderEventMock->expects(self::once())
            ->method('checkContext')
            ->willReturn(true);

        $this->companyReportDataMock->expects(self::once())
            ->method('getCompanyData')
            ->willReturn($this->columns);

        $fakeTags = $this->createFakeCompanyTags();
        $this->companyTagsRepositoryMock->expects(self::once())
            ->method('getAllTagObjects')
            ->willReturn($fakeTags);

        $this->reportSubscriber->onReportBuilder($this->reportBuilderEventMock);
        $tables = $this->reportBuilderEventMock->getTables();

        self::assertArrayHasKey('company_tags', $tables);
        self::assertCount(16, $tables['company_tags']['columns']);
        self::assertCount(17, $tables['company_tags']['filters']);

        $tagFilter = $tables['company_tags']['filters'][ReportSubscriber::COMPANY_TAGS_XREF_PREFIX.'.tag_id'];
        self::assertIsArray($tagFilter);
        self::assertArrayHasKey('list', $tagFilter);
        self::assertCount(4, $tagFilter['list']);
        self::assertEquals([
            '1' => 'a',
            '2' => 'b',
            '3' => 'c',
            '4' => 'd',
        ], $tagFilter['list']);
    }

    public function testOnReportBuilderWithWrongContext(): void
    {
        $this->reportBuilderEventMock->expects(self::once())
            ->method('checkContext')
            ->willReturn(false);
        $this->reportSubscriber->onReportBuilder($this->reportBuilderEventMock);
    }

    public function testGetCompanyTagConditionWrongFilterColumn(): void
    {
        $filter = [
            'column' => 'abcd',
        ];

        $result = $this->reportSubscriber->getCompanyTagCondition($filter);
        self::assertEquals(null, $result);
    }

    public function testGetCompanyTagConditionWhenOperatorEqualsIn(): void
    {
        $filterSubQueryResult = 'filter sub query';
        $filterResult         = 'filter result';
        $subQueryResult       = 'sub query';
        $filterValue          = '3';

        $this->queryBuilderMock->expects(self::once())
            ->method('andWhere')
            ->with($filterSubQueryResult);
        $this->queryBuilderMock->expects(self::once())
            ->method('getSQL')
            ->willReturn($subQueryResult);

        $this->exprMock
            ->expects(self::exactly(2))
            ->method('in')
            ->willReturnCallback(static function (string $field, string $value) use ($subQueryResult, $filterValue, $filterResult, $filterSubQueryResult): string {
                if ($field === ReportSubscriber::COMPANY_TAGS_XREF_PREFIX.'.tag_id') {
                    self::assertSame($filterValue, $value);

                    return $filterSubQueryResult;
                }

                if ($field === ReportSubscriber::COMPANIES_PREFIX.'.id') {
                    self::assertSame('('.$subQueryResult.')', $value);

                    return $filterResult;
                }

                self::fail('Unknown field: '.$field);
            });

        $filter = [
            'column'    => ReportSubscriber::COMPANY_TAGS_XREF_PREFIX.'.tag_id',
            'glue'      => 'and',
            'dynamic'   => null,
            'condition' => 'in',
            'value'     => $filterValue,
        ];

        self::assertSame($filterResult, $this->reportSubscriber->getCompanyTagCondition($filter));
    }

    public function testGetCompanyTagConditionWhenOperatorEqualsNotIn(): void
    {
        $filterSubQueryResult = 'filter sub query';
        $filterResult         = 'filter result';
        $subQueryResult       = 'sub query';
        $filterValue          = '3';

        $this->queryBuilderMock->expects(self::once())
            ->method('andWhere')
            ->with($filterSubQueryResult);
        $this->queryBuilderMock->expects(self::once())
            ->method('getSQL')
            ->willReturn($subQueryResult);

        $this->exprMock
            ->expects(self::once())
            ->method('in')
            ->willReturnCallback(static function (string $field, string $value) use ($filterValue, $filterSubQueryResult): string {
                self::assertSame($filterValue, $value);

                return $filterSubQueryResult;
            });

        $this->exprMock
            ->expects(self::once())
            ->method('notIn')
            ->willReturnCallback(static function (string $field, string $value) use ($subQueryResult, $filterResult): string {
                self::assertSame('('.$subQueryResult.')', $value);

                return $filterResult;
            });

        $filter = [
            'column'    => ReportSubscriber::COMPANY_TAGS_XREF_PREFIX.'.tag_id',
            'glue'      => 'and',
            'dynamic'   => null,
            'condition' => 'notIn',
            'value'     => $filterValue,
        ];

        self::assertSame($filterResult, $this->reportSubscriber->getCompanyTagCondition($filter));
    }

    public function testGetCompanyTagConditionWhenOperatorEqualsEmpty(): void
    {
        $filterResult         = 'filter result';
        $subQueryResult       = 'sub query';
        $this->queryBuilderMock->expects(self::once())
            ->method('getSQL')
            ->willReturn($subQueryResult);

        $this->exprMock
            ->expects(self::once())
            ->method('notIn')
            ->willReturnCallback(static function (string $field, string $value) use ($subQueryResult, $filterResult): string {
                self::assertSame('('.$subQueryResult.')', $value);

                return $filterResult;
            });

        $filter = [
            'column'    => ReportSubscriber::COMPANY_TAGS_XREF_PREFIX.'.tag_id',
            'glue'      => 'and',
            'dynamic'   => null,
            'condition' => 'empty',
        ];

        self::assertSame($filterResult, $this->reportSubscriber->getCompanyTagCondition($filter));
    }

    public function testGetCompanyTagConditionWhenOperatorEqualsNotEmpty(): void
    {
        $filterResult         = 'filter result';
        $subQueryResult       = 'sub query';
        $this->queryBuilderMock->expects(self::once())
            ->method('getSQL')
            ->willReturn($subQueryResult);

        $this->exprMock
            ->expects(self::once())
            ->method('in')
            ->willReturnCallback(static function (string $field, string $value) use ($subQueryResult, $filterResult): string {
                self::assertSame('('.$subQueryResult.')', $value);

                return $filterResult;
            });

        $filter = [
            'column'    => ReportSubscriber::COMPANY_TAGS_XREF_PREFIX.'.tag_id',
            'glue'      => 'and',
            'dynamic'   => null,
            'condition' => 'notEmpty',
        ];

        self::assertSame($filterResult, $this->reportSubscriber->getCompanyTagCondition($filter));
    }
}
