<?php

namespace Acme\Service;

use Concrete\Core\Config\Repository\Repository;

defined('C5_EXECUTE') or die('Access Denied.');

final class UI
{
    /**
     * Major concrete5 / ConcreteCMS version.
     *
     * @var int
     * @readonly
     */
    public $majorVersion;

    /**
     * @var string
     * @readonly
     */
    public $displayNone;

    /**
     * @var string
     * @readonly
     */
    public $faCheck;

    /**
     * @var string
     * @readonly
     */
    public $faExclamationTriangle;

    /**
     * @var string
     * @readonly
     */
    public $faExclamationCircle;

    /**
     * @var string
     * @readonly
     */
    public $faQuestionCircle;

    /**
     * @var string
     * @readonly
     */
    public $faAsterisk;

    /**
     * @var string
     * @readonly
     */
    public $faCheckboxChecked;

    /**
     * @var string
     * @readonly
     */
    public $faCheckboxUnchecked;

    /**
     * @var string
     * @readonly
     */
    public $faRefreshSpinning;

    /**
     * @var string
     * @readonly
     */
    public $faCogSpinning;

    /**
     * @var string
     * @readonly
     */
    public $badgePrimary;

    /**
     * @var string
     * @readonly
     */
    public $badgeSuccess;

    /**
     * @var string
     * @readonly
     */
    public $badgeDanger;

    /**
     * @var string
     * @readonly
     */
    public $badgeInsideButton;

    /**
     * @var string
     * @readonly
     */
    public $defaultButton;

    /**
     * @var string
     * @readonly
     */
    public $inputGroupAddon;

    /**
     * @var string
     * @readonly
     */
    public $floatStart;

    /**
     * @var string
     * @readonly
     */
    public $floatEnd;

    /**
     * @var string
     * @readonly
     */
    public $dropdownMenuAlignedEnd;

    /**
     * @var string
     */
    private $accordionStart;

    /**
     * @var string
     */
    private $accordionEnd;

    /**
     * @var string
     */
    private $accordionTabStart;

    /**
     * @var array
     */
    private $accordionTabStartExpandedMap;

    /**
     * @var string
     */
    private $accordionTabEnd;

    public function __construct(Repository $config)
    {
        $version = $config->get('concrete.version');
        if (version_compare($version, '9') >= 0) {
            $this->initializeV9();
        } else {
            $this->initializeV8();
        }
    }

    /**
     * @param string $id
     * @param string $extraAttributes
     *
     * @return string
     */
    public function startAccordion($id, $extraAttributes = '')
    {
        return sprintf($this->accordionStart, $id, $extraAttributes);
    }

    /**
     * @return string
     */
    public function endAccordion()
    {
        return $this->accordionEnd;
    }

    /**
     * @param string $accordionID
     * @param string $id
     * @param string $headerHtml
     * @param bool $expanded
     *
     * @return string
     */
    public function startAccordionTab($accordionID, $id, $headerHtml, $expanded = false)
    {
        $args = array_merge([$accordionID, $id, $headerHtml], $this->accordionTabStartExpandedMap[(bool) $expanded]);

        return vsprintf($this->accordionTabStart, $args);
    }

    public function endAccordionTab()
    {
        return $this->accordionTabEnd;
    }

    private function initializeV9()
    {
        $this->majorVersion = 9;
        $this->displayNone = 'd-none';
        $this->faCheck = 'fas fa-check';
        $this->faExclamationTriangle = 'fas fa-exclamation-triangle';
        $this->faExclamationCircle = 'fas fa-exclamation-circle';
        $this->faQuestionCircle = 'far fa-question-circle';
        $this->faAsterisk = 'fas fa-asterisk';
        $this->faCheckboxChecked = 'far fa-check-square';
        $this->faCheckboxUnchecked = 'far fa-square';
        $this->faRefreshSpinning = 'fas fa-sync-alt fa-spin';
        $this->faCogSpinning = 'fas fa-cog fa-spin fa-fw';
        $this->badgePrimary = 'badge bg-primary';
        $this->badgeSuccess = 'badge bg-success';
        $this->badgeDanger = 'badge bg-danger';
        $this->badgeInsideButton = 'badge rounded-pill bg-light text-dark p-1';
        $this->defaultButton = 'btn-secondary';
        $this->inputGroupAddon = 'input-group-text';
        $this->floatStart = 'float-start';
        $this->floatEnd = 'float-end';
        $this->dropdownMenuAlignedEnd = 'dropdown-menu-end';
        $this->accordionStart = '<div class="accordion" id="%s" %s>';
        $this->accordionEnd = '</div>';
        $this->accordionTabStart = <<<'EOT'
<div class="accordion-item">
    <div class="accordion-header" id="%2$s-header">
        <h4 class="panel-title">
            <button type="button" class="h2 accordion-button%4$s" data-bs-toggle="collapse" data-bs-target="#%2$s-body" aria-expanded="%5$s" aria-controls="%2$s-body">
                %3$s
            </button>
        </h4>
    </div>
    <div id="%2$s-body" class="accordion-collapse collapse%6$s" aria-labelledby="%2$s-header" data-bs-parent="#%1$s">
        <div class="accordion-body">
EOT
        ;
        $this->accordionTabStartExpandedMap = [
            false => [
                ' collapsed',
                'false',
                '',
            ],
            true => [
                '',
                'true',
                ' show',
            ],
        ];
        $this->accordionTabEnd = <<<'EOT'
        </div>
    </div>
</div>
EOT
        ;
    }

    private function initializeV8()
    {
        $this->majorVersion = 8;
        $this->displayNone = 'hide';
        $this->faCheck = 'fa fa-check';
        $this->faExclamationTriangle = 'fa fa-exclamation-triangle';
        $this->faExclamationCircle = 'fa fa-exclamation-circle';
        $this->faQuestionCircle = 'fa fa-question-circle';
        $this->faAsterisk = 'fa fa-asterisk';
        $this->faCheckboxChecked = 'fa fa-check-square-o';
        $this->faCheckboxUnchecked = 'fa fa-square-o';
        $this->faRefreshSpinning = 'fa fa-refresh fa-spin';
        $this->faCogSpinning = 'fa fa-cog fa-spin fa-fw';
        $this->badgePrimary = 'label label-primary';
        $this->badgeSuccess = 'label label-success';
        $this->badgeDanger = 'label label-danger';
        $this->badgeInsideButton = 'label';
        $this->defaultButton = 'btn-default';
        $this->inputGroupAddon = 'input-group-addon';
        $this->floatStart = 'pull-left';
        $this->floatEnd = 'pull-right';
        $this->dropdownMenuAlignedEnd = 'dropdown-menu-right';
        $this->accordionStart = '<div class="panel-group" role="tablist" id="%s" %s>';
        $this->accordionEnd = '</div>';
        $this->accordionTabStart = <<<'EOT'
<div class="panel panel-default">
    <div class="panel-heading" role="tab" id="%2$s-header">
        <h4 class="panel-title">
            <a role="button" data-toggle="collapse" data-parent="#%1$s" href="#%2$s-body">
                %3$s
            </a>
        </h4>
    </div>
    <div id="%2$s-body" class="panel-collapse collapse%4$s" role="tabpanel">
        <div class="panel-body">
EOT
        ;
        $this->accordionTabStartExpandedMap = [
            false => [
                '',
            ],
            true => [
                ' in',
            ],
        ];
        $this->accordionTabEnd = <<<'EOT'
        </div>
    </div>
</div>
EOT
        ;
    }
}
