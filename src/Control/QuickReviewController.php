<?php

namespace Sunnysideup\AutomatedContentManagement\Control;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\RequestHandler;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\ArrayData;
use Sunnysideup\AutomatedContentManagement\Api\DataObjectUpdateCMSFieldsHelper;
use Sunnysideup\AutomatedContentManagement\Model\Instruction;
use Sunnysideup\AutomatedContentManagement\Model\RecordProcess;
use Sunnysideup\Selections\Model\Selection;

class QuickReviewController extends Controller
{

    private static $url_segment = 'llm-quick-review';
    private static $allowed_actions = [
        'index' => 'CMS_ACCESS_LLMEDITOR',
        'show' => 'CMS_ACCESS_LLMEDITOR',
    ];

    protected string $classNameUnescaped = '';
    protected string $classNameEscaped = '';
    protected string $fieldName = '';
    protected int $days = 7;
    protected int $limitPerList = 9999;


    public function index()
    {
        return $this->show($this->getRequest());
    }



    public function show($request)
    {
        parent::init();
        if ($request) {
            if ($request->param('ID')) {
                $classNameEscaped = $request->param('ID');
                $classNameUnescaped = str_replace('-', '\\', $classNameEscaped);
                if (class_exists($classNameUnescaped)) {
                    $this->classNameUnescaped = $classNameUnescaped;
                    $this->classNameEscaped = $classNameEscaped;
                    if ($request->param('OtherID')) {
                        $fieldName = $request->param('OtherID');
                        if (in_array($fieldName, $this->getListOfFieldsInner($this->classNameUnescaped), true)) {
                            $this->fieldName = $fieldName;
                        }
                    }
                }
            }
            if ($request->getVar('days')) {
                $this->days = (int) $request->getVar('days');
            }
            if ($request->getVar('limit')) {
                $this->limitPerList = (int) $request->getVar('limit');
            }
        }
        return [];
        // Add any necessary initialization code here
    }

    public function getTitle()
    {
        $t =  'LLM Quick Review';
        $t .= ' -  for the last ' . $this->days . ' days';
        if ($this->classNameUnescaped) {
            $obj = Injector::inst()->get($this->classNameUnescaped);
            $t .= ' - for "' . $obj->i18n_plural_name() . '"';
            if ($this->fieldName) {
                $fieldLabels = $obj->fieldLabels();
                $t .= ' (' . ($fieldLabels[$this->fieldName] ?? $this->fieldName) . ')';
            }
        }
        return $t;
    }

    public function ShowDetails(): bool
    {
        if ($this->classNameUnescaped && $this->fieldName) {
            return true;
        }
        return false;
    }

    protected function getListOriginalUpdated(?string $classNameUnescaped = null, ?string $fieldName = null, ?int $limit = null): DataList
    {
        $filter = $this->BasicFilter($classNameUnescaped, $fieldName);
        $filter['OriginalUpdated'] = true;
        return RecordProcess::get()->filter($filter)->sort('LastEdited', 'DESC')
            ->limit($limit ?: $this->limitPerList);
    }

    protected function getListAnswerCompleted(?string $classNameUnescaped = null, ?string $fieldName = null, ?int $limit = null): DataList
    {
        $filter = $this->BasicFilter($classNameUnescaped, $fieldName);
        $filter['Completed'] = true;
        return RecordProcess::get()->filter($filter)->sort('LastEdited', 'DESC')
            ->limit($limit ?: $this->limitPerList);
    }

    protected function BasicFilter(?string $classNameUnescaped = null, ?string $fieldName = null): array
    {
        $days = $this->days ?: 7;
        $date = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
        $filter = [
            'LastEdited:GreaterThan' => $date,
        ];
        $filter['Skip'] = false;
        $filter['Rejected'] = false;
        $instructions = Instruction::get();
        if ($classNameUnescaped || $this->classNameUnescaped) {
            $test = $classNameUnescaped ?: $this->classNameUnescaped;
            $instructions = $instructions->filter('ClassNameToChange', $test);
        }
        if ($fieldName || $this->fieldName) {
            if ($fieldName !== 'All') {
                $test = $fieldName ?: $this->fieldName;
                $instructions = $instructions->filter('FieldToChange', $test);
            }
        }
        $filter['InstructionID'] = $instructions->columnUnique('ID');
        return $filter;
    }


    public function Link($action = null): string
    {
        return Controller::join_links(Director::baseURL(), self::config()->get('url_segment'), $action);
    }

    protected function getListOfClasses(): ArrayList
    {

        $list = $this->getListOfClassesInner();
        $al = ArrayList::create();
        foreach ($list as $classNameUnescaped) {
            if (!class_exists($classNameUnescaped)) {
                continue;
            }
            $name = Injector::inst()->get($classNameUnescaped)->i18n_plural_name();
            $classNameEscaped = str_replace('\\', '-', $classNameUnescaped);

            $arrayData  = ArrayData::create([
                'ClassName' => $classNameEscaped,
                'Name' => $name,
                'Link' => $this->Link('show/' . $classNameEscaped) . '?days=' . $this->days,
                'Fields' => $this->getListOfFields(str_replace('-', '\\', $classNameUnescaped)), // pass original class name
                'CountOriginalsUpdated' => $this->getListOriginalUpdated($classNameUnescaped, 'All')->count(),
                'CountAnswersCompleted' => $this->getListAnswerCompleted($classNameUnescaped, 'All')->count(),
            ]);
            $al->push($arrayData);
        }
        return $al;
    }

    protected function getListOfClassesInner(): array
    {
        return array_unique(Instruction::get()
            ->columnUnique('ClassNameToChange'));
    }

    protected function getListOfFields(string $classNameUnescaped): ArrayList
    {

        $al = ArrayList::create();
        if (!class_exists($classNameUnescaped)) {
            return $al;
        }
        $list = $this->getListOfFieldsInner($classNameUnescaped);
        foreach ($list as $fieldName) {
            $obj = Injector::inst()->get($classNameUnescaped);
            $dbs = $obj->config()->get('db');
            if (!array_key_exists($fieldName, $dbs)) {
                continue;
            }
            $labels = $obj->fieldLabels();
            $name = $labels[$fieldName] ?? $fieldName;
            $classNameEscaped = str_replace('\\', '-', $classNameUnescaped);
            $arrayData  = ArrayData::create([
                'ClassName' => $classNameEscaped,
                'FieldName' => $fieldName,
                'Name' => $name,
                'Link' => $this->Link('show/' . $classNameEscaped . '/' . $fieldName) . '?days=' . $this->days,
                'CountOriginalsUpdated' => $this->getListOriginalUpdated($classNameUnescaped, $fieldName)->count(),
                'CountAnswersCompleted' => $this->getListAnswerCompleted($classNameUnescaped, $fieldName)->count(),

            ]);
            $al->push($arrayData);
        }
        return $al;
    }

    protected function getListOfFieldsInner(string $classNameUnescaped): array
    {
        return array_unique(Instruction::get()
            ->filter(['ClassNameToChange' => $classNameUnescaped])
            ->columnUnique('FieldToChange'));
    }
}
