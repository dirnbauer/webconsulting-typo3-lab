<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\ViewHelpers\StageWall;

use TYPO3\CMS\Core\Domain\RecordInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Puts an element's place in the "Stage and Wall" plan on its rendered HTML:
 * the data attributes go on the element's root tag (`data-sw`, column, span,
 * rows) and on the anchors TYPO3 renders before it (`data-sw-anchor` plus the
 * same placement, so `#c123` links land on the element). Nothing is wrapped
 * and nothing moves: the markup, the reading order and the tab order are the
 * ones of the plain start page. In the Visual Editor the attributes go
 * inside its <ve-content-element> wrapper.
 *
 * ```
 *   <sp:stageWall.cell plan="{plan}" record="{record}"><f:render.record record="{record}"/></sp:stageWall.cell>
 * ```
 */
final class CellViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    protected $escapeChildren = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('plan', 'array', 'The plan of sp:stageWall.plan', true);
        $this->registerArgument('record', RecordInterface::class, 'The content element', true);
    }

    public function render(): string
    {
        $html = (string)$this->renderChildren();
        $record = $this->arguments['record'];
        $plan = $this->arguments['plan'];
        if (!$record instanceof RecordInterface || !is_array($plan) || !is_array($plan['cells'] ?? null)) {
            return $html;
        }
        $attributes = $plan['cells'][$record->getUid()] ?? null;
        return is_array($attributes) ? self::annotate($html, $attributes) : $html;
    }

    /**
     * @param array<array-key, mixed> $attributes
     */
    public static function annotate(string $html, array $attributes): string
    {
        $element = '';
        $placement = '';
        foreach ($attributes as $name => $value) {
            if (!is_string($name) || !preg_match('/^data-sw(-[a-z]+)?$/', $name) || !is_scalar($value)) {
                continue;
            }
            $value = htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5);
            $attribute = ' ' . $name . ($value === '' ? '' : '="' . $value . '"');
            $element .= $attribute;
            if ($name === 'data-sw') {
                $placement .= ' data-sw-anchor="' . $value . '"';
            } elseif ($name !== 'data-sw-dock') {
                $placement .= $attribute;
            }
        }
        if ($element === '') {
            return $html;
        }
        // Skip the Visual Editor's wrapper, whitespace and comments; mark the
        // anchors; the first other tag is the element's root.
        $offset = 0;
        $length = strlen($html);
        while ($offset < $length) {
            if (preg_match('/\G(?:\s+|<!--.*?-->|<ve-content-element\b[^>]*>)/s', $html, $skip, 0, $offset) === 1) {
                $offset += strlen($skip[0]);
                continue;
            }
            if (preg_match('/\G<a\b(?=[^>]*\bid=)([^>]*)>\s*<\/a>/i', $html, $anchor, 0, $offset) === 1) {
                $tag = '<a' . rtrim($anchor[1]) . $placement . '></a>';
                $html = substr($html, 0, $offset) . $tag . substr($html, $offset + strlen($anchor[0]));
                $offset += strlen($tag);
                $length = strlen($html);
                continue;
            }
            if (preg_match('/\G<([a-z][a-z0-9-]*)\b([^>]*?)(\/?)>/i', $html, $root, 0, $offset) === 1) {
                $tag = '<' . $root[1] . rtrim($root[2]) . $element . $root[3] . '>';
                return substr($html, 0, $offset) . $tag . substr($html, $offset + strlen($root[0]));
            }
            break;
        }
        return $html;
    }
}
