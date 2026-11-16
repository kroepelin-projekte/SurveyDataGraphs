<?php

class ilSurveyDataGraphsDB
{
    private ilDBInterface $ilDB;

    private int $user_id;

    /**
     * Query results per request. Several elements on one page and repeated
     * calls within one element read the same rows, so each query runs once.
     *
     * @var array<string, mixed>
     */
    private static array $cache = [];

    public function __construct()
    {
        global $DIC;
        $this->ilDB = $DIC->database();
        $this->user_id = $DIC->user()->getId();
    }

    private function cached(string $a_key, callable $a_query): mixed
    {
        $key = $this->user_id . ':' . $a_key;
        if (!array_key_exists($key, self::$cache)) {
            self::$cache[$key] = $a_query();
        }
        return self::$cache[$key];
    }

    /**
     * @return list<int|string>
     */
    public function getSvyQuestions(int $a_obj_id): array
    {
        return $this->cached(__FUNCTION__ . ':' . $a_obj_id, fn(): array => $this->querySvyQuestions($a_obj_id));
    }

    /**
     * @return list<int|string>
     */
    private function querySvyQuestions(int $a_obj_id): array
    {
        $sql = sprintf(
            "SELECT svy_question.question_id FROM svy_question " .
            "JOIN svy_quest_skill ON question_id = q_id " .
            "WHERE obj_fi = %s AND base_skill_id IS NOT NULL ",
            $this->ilDB->quote($a_obj_id, "int")
        );
        $query = $this->ilDB->query($sql);
        $result = array();
        while ($row = $query->fetchRow()) {
            $result[] = $row['question_id'];
        }
        return $result;
    }

    /**
     * @return list<int|string>
     */
    public function getSvyAnswers(int $a_obj_id): array
    {
        return $this->cached(__FUNCTION__ . ':' . $a_obj_id, fn(): array => $this->querySvyAnswers($a_obj_id));
    }

    /**
     * @return list<int|string>
     */
    private function querySvyAnswers(int $a_obj_id): array
    {
        $sql = sprintf(
            "SELECT answer_id FROM svy_answer " .
            "JOIN svy_finished ON active_fi = finished_id " .
            "JOIN svy_quest_skill ON question_fi = q_id " .
            "JOIN svy_question ON question_id = q_id " .
            "WHERE obj_fi = %s AND user_fi = %s",
            $this->ilDB->quote($a_obj_id, "int"),
            $this->ilDB->quote($this->user_id, "int")
        );
        $query = $this->ilDB->query($sql);
        $result = array();
        while ($row = $query->fetchRow()) {
            $result[] = $row['answer_id'];
        }
        return $result;
    }

    public function getFinishedSvyTimestamp(int $a_obj_id): string
    {
        return $this->cached(__FUNCTION__ . ':' . $a_obj_id, fn(): string => $this->queryFinishedSvyTimestamp($a_obj_id));
    }

    private function queryFinishedSvyTimestamp(int $a_obj_id): string
    {
        $sql = sprintf(
            "SELECT svf.tstamp, svsv.obj_fi FROM svy_finished AS svf " .
            "JOIN svy_svy AS svsv ON (svsv.survey_id = svf.survey_fi) " .
            "WHERE svsv.obj_fi = %s AND svf.user_fi = %s AND state = 1 ",
            $this->ilDB->quote($a_obj_id, "int"),
            $this->ilDB->quote($this->user_id, "int"),
        );
        $query = $this->ilDB->query($sql);
        $result = "";
        while ($row = $query->fetchRow()) {
            $result = date('d. M. Y, H:i', $row['tstamp']);
        }
        return $result;
    }

    public function getSvyProgress(string $a_obj_id): int
    {
        $pro = 0;
        if ($a_obj_id != "") {
            $answers = $this->getSvyAnswers($a_obj_id);
            $questions = $this->getSvyQuestions($a_obj_id);
            if (!empty($questions)) {
                $progress = (count($answers) * 100 / count($questions));
                $pro = round($progress);
            }
        }
        return $pro;
    }

    public function getFinishedSvyObjs(array $a_svy_obj_ids): array
    {
        $svy_fin = array();
        foreach ($a_svy_obj_ids as $a_svy_obj_id) {
            $finished = $this->cached(
                'finished:' . $a_svy_obj_id,
                fn(): bool => (bool) ilObjSurveyAccess::_lookupFinished((int) $a_svy_obj_id, $this->user_id)
            );
            if ($finished) {
                $svy_fin[] = $a_svy_obj_id;
            }
        }
        return $svy_fin;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getFinishedAnswerSkillData(int $a_obj_id): array
    {
        return $this->cached(__FUNCTION__ . ':' . $a_obj_id, fn(): array => $this->queryFinishedAnswerSkillData($a_obj_id));
    }

    /**
     * @return array<int|string, mixed>
     */
    private function queryFinishedAnswerSkillData(int $a_obj_id): array
    {
        $sql = sprintf(
            "SELECT quest_skill.base_skill_id, SUM(answer.value+1) AS sum_skill_value, COUNT(answer.question_fi) AS count_answers FROM svy_question AS question " .
            "LEFT OUTER JOIN svy_answer AS answer ON question_fi = question_id " .
            "LEFT OUTER JOIN svy_finished ON answer.active_fi=svy_finished.finished_id " .
            "LEFT OUTER JOIN svy_svy ON svy_svy.survey_id=svy_finished.survey_fi " .
            "LEFT OUTER JOIN svy_quest_skill AS quest_skill ON q_id = question_id " .
            "WHERE svy_svy.obj_fi = %s AND svy_finished.user_fi = %s " .
            "AND quest_skill.base_skill_id IS NOT NULL " .
            "GROUP BY quest_skill.base_skill_id ",
            $this->ilDB->quote($a_obj_id, "int"),
            $this->ilDB->quote($this->user_id, "int")
        );
        $query = $this->ilDB->query($sql);
        $result = array();
        while ($row = $query->fetchRow()) {
            $result[$row['base_skill_id']] = $row;
        }
        return $result;
    }

    public function getBaseSkillThreshold(int $a_obj_id, int $a_base_skill_id): array
    {
        $sql = sprintf(
            "SELECT threshold, level_id FROM svy_skill_threshold WHERE survey_id = %s AND base_skill_id = %s ",
            $this->ilDB->quote($a_obj_id, "int"),
            $this->ilDB->quote($a_base_skill_id, "int")
        );
        $query = $this->ilDB->query($sql);
        $result = array();
        while ($row = $query->fetchRow()) {
            $result[] = $row;
        }
        return $result;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getBaseSkillAnswers(int $a_obj_id, int $a_base_skill_id): array
    {
        return $this->cached(__FUNCTION__ . ':' . $a_obj_id . ':' . $a_base_skill_id, fn(): array => $this->queryBaseSkillAnswers($a_obj_id, $a_base_skill_id));
    }

    /**
     * @return array<int|string, mixed>
     */
    private function queryBaseSkillAnswers(int $a_obj_id, int $a_base_skill_id): array
    {
        $sql = sprintf(
            "SELECT question_fi, (svy_answer.value+1) AS points FROM svy_answer " .
            "JOIN svy_question ON question_fi = question_id " .
            "JOIN svy_quest_skill ON question_fi = q_id " .
            "WHERE obj_fi = %s AND base_skill_id = %s " .
            "AND active_fi = (SELECT finished_id FROM svy_finished " .
            "JOIN svy_svy ON survey_fi = survey_id " .
            "WHERE obj_fi = %s AND svy_finished.user_fi = %s " .
            "ORDER BY svy_finished.tstamp DESC LIMIT 1) ",
            $this->ilDB->quote($a_obj_id, "int"),
            $this->ilDB->quote($a_base_skill_id, "int"),
            $this->ilDB->quote($a_obj_id, "int"),
            $this->ilDB->quote($this->user_id, "int")
        );
        $query = $this->ilDB->query($sql);

        $result = array();
        while ($row = $query->fetchRow()) {
            $result[] = $row;
        }
        return $result;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getSkillResourceRefId(int $a_level_id): array
    {
        return $this->cached(__FUNCTION__ . ':' . $a_level_id, fn(): array => $this->querySkillResourceRefId($a_level_id));
    }

    /**
     * @return array<int|string, mixed>
     */
    private function querySkillResourceRefId(int $a_level_id): array
    {
        $sql = sprintf(
            "SELECT DISTINCT rep_ref_id FROM skl_skill_resource " .
            "JOIN svy_skill_threshold ON skl_skill_resource.level_id = svy_skill_threshold.level_id " .
            "WHERE svy_skill_threshold.level_id = %s ",
            $this->ilDB->quote($a_level_id, "int")
        );
        $query = $this->ilDB->query($sql);
        $result = array();
        while ($row = $query->fetchRow()) {
            $result[] = $row;
        }
        return $result;
    }

    public function getMAXQuestionValue(int $a_obj_id): int
    {
        return $this->cached(__FUNCTION__ . ':' . $a_obj_id, fn(): int => $this->queryMAXQuestionValue($a_obj_id));
    }

    private function queryMAXQuestionValue(int $a_obj_id): int
    {
        $sql = sprintf(
            "SELECT MAX(svy_variable.value1) as max FROM svy_variable " .
            "JOIN svy_question ON svy_variable.question_fi = svy_question.question_id " .
            "WHERE svy_question.obj_fi = %s ",
            $this->ilDB->quote($a_obj_id, "int")
        );
        $query = $this->ilDB->query($sql);
        $result = array();
        while ($row = $query->fetchRow()) {
            $result = $row;
        }
        return (int) ($result['max'] ?? 0);
    }

    public function getMAXTotalQuestValue(int $a_obj_id): int
    {
        $sql = sprintf(
            "SELECT COUNT(DISTINCT question_id) * max(value1) as max FROM svy_variable " .
            "JOIN svy_question ON svy_variable.question_fi = svy_question.question_id " .
            "WHERE svy_question.obj_fi = %s ",
            $this->ilDB->quote($a_obj_id, "int")
        );

        $query = $this->ilDB->query($sql);
        $result = array();
        while ($row = $query->fetchRow()) {
            $result = $row;
        }
        return intval($result['max']);
    }
}
