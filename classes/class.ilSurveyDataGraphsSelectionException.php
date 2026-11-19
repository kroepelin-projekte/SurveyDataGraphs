<?php

declare(strict_types=1);

/**
 * Thrown when the survey selection of an element cannot be used.
 *
 * Carries a plugin language key instead of a finished text, so the GUI
 * translates every message the same way. The ref id, if any, is filled into
 * the translated text as an integer.
 */
class ilSurveyDataGraphsSelectionException extends ilException
{
    public const string SVY_NOT_FOUND = 'msg_svy_not_found';

    public const string SVY_OFFLINE = 'msg_svy_offline';

    public const string SVY_NO_COMPETENCES = 'msg_svy_no_competences';

    public const string SVY_NO_THRESHOLDS = 'msg_svy_no_thresholds';

    public const string SVY_NO_COMPETENCE_QUESTIONS = 'msg_svy_no_competence_questions';

    public const string SVY_DIFFERENT_COMPETENCES = 'msg_svy_different_competences';

    public const string NO_LEVELS = 'msg_no_levels';

    private string $lang_key;

    private ?int $ref_id;

    public function __construct(string $a_lang_key, ?int $a_ref_id = null)
    {
        parent::__construct($a_lang_key);
        $this->lang_key = $a_lang_key;
        $this->ref_id = $a_ref_id;
    }

    public function getLangKey(): string
    {
        return $this->lang_key;
    }

    /**
     * The survey the message refers to, or null if it concerns the whole selection.
     */
    public function getRefId(): ?int
    {
        return $this->ref_id;
    }
}
