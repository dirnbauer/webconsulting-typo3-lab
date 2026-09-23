<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\ContentAudit;

/**
 * What a text field is for, decided by its name.
 *
 * The same field names recur across hundreds of Content Blocks (`header`,
 * `subheadline`, `badge_text`, `primary_button_text` ...), so the name is a
 * reliable guide to which limits apply. Fields that hold identifiers rather
 * than prose (icons, variants, anchors, URLs) are SKIP and never checked.
 */
enum FieldRole: string
{
    case PageTitle = 'page_title';
    case Headline = 'headline';
    case Eyebrow = 'eyebrow';
    case Button = 'button';
    case Lead = 'lead';
    case Card = 'card';
    case FaqAnswer = 'faq_answer';
    case Body = 'body';
    case Meta = 'meta';
    case Alt = 'alt';
    case Other = 'other';
    case Skip = 'skip';

    /**
     * @param string $field column or JSON key, e.g. `primary_button_text`
     * @param string $table table the field belongs to; `pages` and `sys_file_reference` have their own rules
     */
    public static function fromField(string $field, string $table = 'tt_content'): self
    {
        $name = strtolower($field);

        if ($table === 'sys_file_reference' || $table === 'sys_file_metadata') {
            return match ($name) {
                'alternative' => self::Alt,
                'title' => self::Headline,
                'description' => self::Other,
                default => self::Skip,
            };
        }
        if ($table === 'pages') {
            return match ($name) {
                'title', 'nav_title', 'seo_title', 'og_title', 'twitter_title' => self::PageTitle,
                'description', 'og_description', 'twitter_description' => self::Meta,
                'abstract', 'subtitle' => self::Lead,
                default => self::Skip,
            };
        }

        if (preg_match('/(^|_)(icon|variant|style|layout|color|colour|alignment|align|position|size|anchor|id|uid|url|href|link|email|phone|tel|slug|class|target|type|format|mode|theme|preset|ratio|lang|language|code|html|svg|embed|iframe|video_id|provider|key|token|hash|date|time|timestamp|datetime|number|value_raw|lat|lng|latitude|longitude|zoom)$/', $name) === 1
            && preg_match('/(text|label|title|caption)$/', $name) !== 1) {
            return self::Skip;
        }
        if (preg_match('/(^|_)(button|cta|link|action)(_\w+)?_(text|label)$|^(button|cta|link|action)_?(text|label)$/', $name) === 1) {
            return self::Button;
        }
        if (preg_match('/(^|_)(eyebrow|badge|badge_text|kicker|overline|tag|tagline_short|pill|chip|status)$/', $name) === 1) {
            return self::Eyebrow;
        }
        if (preg_match('/^(header|headline|heading|title|name|question|subheader_title)$|(_title|_headline|_heading)$/', $name) === 1) {
            return self::Headline;
        }
        if (preg_match('/^(subheader|subheadline|subtitle|lead|intro|teaser|excerpt|tagline|summary|abstract)$|(_subheadline|_subtitle|_lead|_intro)$/', $name) === 1) {
            return self::Lead;
        }
        if ($name === 'answer' || str_ends_with($name, '_answer')) {
            return self::FaqAnswer;
        }
        if (preg_match('/^(description|text|caption|quote|content|note|details|detail|benefit)$|(_description|_text|_caption|_note)$/', $name) === 1) {
            return self::Card;
        }
        if (preg_match('/^(bodytext|body|richtext|article|copy|message)$|(_bodytext|_body|_richtext)$/', $name) === 1) {
            return self::Body;
        }
        if (in_array($name, ['alternative', 'alt', 'alt_text'], true)) {
            return self::Alt;
        }

        return self::Other;
    }
}
