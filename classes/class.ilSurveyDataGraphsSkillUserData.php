<?php

use ILIAS\Data\URI;

class ilSurveyDataGraphsSkillUserData
{
    private ilSurveyDataGraphsDB $data;

    /** @var list<int> */
    private array $obj_ids;

    /** @var array<int|string, mixed> */
    private array $colors;

    /** @var array<int|string, mixed> */
    private array $base_skills;

    private mixed $dic;

    /** @var array<int|string, mixed> */
    private array $questions;

    private int $progress_level;

    /** @var array<int|string, mixed> */
    private array $ref_ids;

    /** @var array<int|string, mixed> */
    private array $sum_thresholds;

    /** @var array<int|string, mixed> */
    private array $level_data;

    private string $chart_scale_option;

    private int $chart_legend_hidden_level;

    private string $sdg_object_title;

    private string $chart_title;

    private string $placeholder;

    private string $x_scale_title;

    private string $y_scale_title;

    /** @var array<int|string, mixed> */
    private array $thresholds;

    private int $max_level_value;

    private ilPlugin $plugin;

    private bool $configuration_valid;

    private ?bool $requirements = null;

    /** @var array<string, mixed>|null */
    private ?array $chart_data = null;

    /** @var array<int|string, mixed>|null */
    private ?array $level = null;

    /** @var array<int|string, mixed>|null */
    private ?array $answer_data_for_all = null;

    /**
     * Every property is read with a default. Pages from an import, a copy or
     * an older plugin version may lack some of them; such an element shows a
     * hint instead of breaking the whole page.
     *
     * @param array<string, mixed> $a_properties
     */
    public function __construct(array $a_properties, ilPlugin $a_plugin)
    {
        global $DIC;
        $this->dic = $DIC;
        $this->plugin = $a_plugin;
        $this->data = new ilSurveyDataGraphsDB();

        $this->base_skills = $this->decodeArray($a_properties, ilSurveyDataGraphsPluginGUI::EDIT_CONF_SI_SKILLDATA);
        $this->level_data = $this->decodeArray($a_properties, ilSurveyDataGraphsPluginGUI::EDIT_CONF_LEVELDATA);
        $this->colors = $this->decodeArray($a_properties, ilSurveyDataGraphsPluginGUI::EDIT_CONF_SI_COLOR);
        $this->sum_thresholds = $this->decodeArray($a_properties, ilSurveyDataGraphsPluginGUI::EDIT_CONF_SKL_THRESHOLDS);
        $this->thresholds = $this->decodeArray($a_properties, ilSurveyDataGraphsPluginGUI::EDIT_CONF_THRESHOLDS);
        $this->questions = $this->decodeArray($a_properties, ilSurveyDataGraphsPluginGUI::EDIT_CONF_SVY_QUESTIONS);
        $this->ref_ids = $this->decodeArray($a_properties, ilSurveyDataGraphsPluginGUI::EDIT_CONF_SVY_SELECTION);

        $this->sdg_object_title = (string) ($a_properties[ilSurveyDataGraphsPluginGUI::EDIT_CONF_PARENT_TITLE] ?? '');
        $this->progress_level = (int) ($a_properties[ilSurveyDataGraphsPluginGUI::EDIT_CONF_SI_PROC_LIMIT] ?? 100);
        $this->placeholder = (string) ($a_properties[ilSurveyDataGraphsPluginGUI::EDIT_CONF_ACCESS_PLACEHOLDER] ?? '');
        $this->chart_title = (string) ($a_properties[ilSurveyDataGraphsPluginGUI::EDIT_CONF_DIAGRAM_TITLE] ?? '');
        $this->chart_scale_option = (string) ($a_properties[ilSurveyDataGraphsPluginGUI::EDIT_CONF_SCALE_SETTING_OPTION]
            ?? ilSurveyDataGraphsPluginGUI::VIEW_SCALE_SETTING_OPTION_LEVEL);
        $this->chart_legend_hidden_level = (int) ($a_properties[ilSurveyDataGraphsPluginGUI::EDIT_HIDDEN_LEGEND_LEVEL]
            ?? count($this->base_skills));
        $this->x_scale_title = (string) ($a_properties[ilSurveyDataGraphsPluginGUI::X_SCALE_TITLE] ?? 'x');
        $this->y_scale_title = (string) ($a_properties[ilSurveyDataGraphsPluginGUI::Y_SCALE_TITLE] ?? 'y');
        $this->max_level_value = (int) ($a_properties[ilSurveyDataGraphsPluginGUI::MAX_LEVEL_VALUE] ?? 0);

        $this->obj_ids = $this->getSvyObjIds($this->ref_ids);
        $this->configuration_valid = $this->obj_ids !== []
            && !in_array(0, $this->obj_ids, true)
            && $this->base_skills !== [];
    }

    /**
     * @param array<string, mixed> $a_properties
     * @return array<int|string, mixed>
     */
    private function decodeArray(array $a_properties, string $a_key): array
    {
        $value = $a_properties[$a_key] ?? null;
        if (!is_string($value) || $value === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * A ref id without object (survey deleted) yields 0 and marks the
     * configuration as inconsistent.
     *
     * @param array<int|string, mixed> $a_svy_ref_ids
     * @return list<int>
     */
    private function getSvyObjIds(array $a_svy_ref_ids): array
    {
        $svy_obj_ids = [];
        foreach ($a_svy_ref_ids as $a_svy_ref_id) {
            $svy_obj_ids[] = ilObject::_lookupObjectId((int) $a_svy_ref_id);
        }
        return $svy_obj_ids;
    }

    /**
     * False if the stored settings are incomplete or point to a survey that
     * no longer exists. The element then shows a hint instead of a chart.
     */
    public function isConfigurationValid(): bool
    {
        return $this->configuration_valid;
    }

    public function getRequirements(): bool
    {
        if ($this->requirements !== null) {
            return $this->requirements;
        }
        $this->requirements = $this->checkRequirements();
        return $this->requirements;
    }

    private function checkRequirements(): bool
    {
        if (!$this->configuration_valid) {
            return false;
        }
        $fi_svy_objs = $this->data->getFinishedSvyObjs($this->obj_ids);
        if (!empty(array_diff($this->obj_ids, $fi_svy_objs))) {
            return false;
        }
        foreach ($fi_svy_objs as $obj_id) {
            if (!($this->data->getSvyProgress($obj_id) >= $this->progress_level)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getResultListData(): array
    {
        if (!$this->configuration_valid) {
            return [];
        }
        $i = 1;
        $result = [];
        foreach ($this->obj_ids as $obj_id) {
            $result[] = [
                "id" => $i++,
                "date" => $this->data->getFinishedSvyTimestamp($obj_id),
                "answers" => $this->data->getSvyAnswers($obj_id),
                "questions" => $this->data->getSvyQuestions($obj_id)
            ];
        }
        return $result;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getBaseSkillData(): array
    {
        return $this->base_skills;
    }

    /**
     * @return list<int>
     */
    public function getObjIds(): array
    {
        return $this->obj_ids;
    }

    public function getChartPlaceholder(): string
    {
        return $this->placeholder;
    }

    public function getTplTitle(): string
    {
        return $this->sdg_object_title;
    }

    public function getChartScaleOption(): string
    {
        return $this->chart_scale_option;
    }

    public function getTplClassname(): string
    {
        if (count($this->obj_ids) === 1) {
            $result = "div_" . ilSurveyDataGraphsPluginGUI::SINGLE_SI;
        } else {
            $result = "div_" . ilSurveyDataGraphsPluginGUI::MULTI_SI;
        }
        return $result;
    }

    /**
     * @return array<int|string, int>
     */
    private function getSvyAnswerSkillValues(int $a_obj_id): array
    {
        $finishedAnswerSkillData = $this->data->getFinishedAnswerSkillData($a_obj_id);
        $result = [];
        foreach ($this->base_skills as $skl_id => $base_skill) {
            if (isset($finishedAnswerSkillData[$skl_id])) {
                $result[$skl_id] = intval($finishedAnswerSkillData[$skl_id]['sum_skill_value']);
            } else {
                $result[$skl_id] = 0;
            }
        }
        return $result;
    }

    /**
     * A competence without assigned questions has no threshold; it would
     * divide by zero and is left out.
     *
     * @return array<int|string, array<int|string, float|int>>
     */
    private function getDynamicThresholdsForSvy(int $a_obj_id): array
    {
        $result = [];
        foreach ($this->thresholds as $skl_id => $threshold) {
            $count_questions = count($this->questions[$skl_id][$a_obj_id] ?? []);
            if ($count_questions === 0 || !isset($threshold[$a_obj_id])) {
                continue;
            }
            $count_answers = count($this->data->getBaseSkillAnswers($a_obj_id, (int) $skl_id));
            foreach ($threshold[$a_obj_id] as $item) {
                $result[$skl_id][$item['id']] = ($item['threshold'] / $count_questions * $count_answers);
            }
        }
        return $result;
    }

    /**
     * @return array<int|string, array<int, array<string, mixed>>>
     */
    private function getSkillEvaluationForSvyObjects(): array
    {
        $result = [];
        foreach ($this->obj_ids as $obj_id) {
            $svy_answer_values = $this->getSvyAnswerSkillValues($obj_id);
            $svy_skill_threshold = $this->getDynamicThresholdsForSvy($obj_id);

            foreach ($svy_skill_threshold as $skl_id => $threshold) {
                $count_q = count($this->questions[$skl_id][$obj_id] ?? []);
                $count_a = count($this->data->getBaseSkillAnswers($obj_id, (int) $skl_id));
                $points = $svy_answer_values[$skl_id] ?? 0;
                foreach ($threshold as $lvl_id => $value) {
                    $level_data = $this->level_data[$skl_id][$lvl_id] ?? null;
                    if ($level_data === null) {
                        continue;
                    }
                    if ($points <= $value && $points != 0) {
                        $result[$skl_id][$obj_id] = [
                            "skill_id" => $skl_id,
                            "level_id" => $lvl_id,
                            "questions" => $count_q,
                            "answers" => $count_a,
                            "points" => $points,
                            "points_answer" => $count_a >= 1 ? ($points / $count_a) : $points,
                            "level_title" => $level_data['title'],
                            "level" => intval($level_data['nr']),
                        ];
                        break;
                    } elseif ($points == 0) {
                        $result[$skl_id][$obj_id] = [
                            "skill_id" => $skl_id,
                            "level_id" => $lvl_id,
                            "questions" => $count_q,
                            "answers" => 0,
                            "points" => 'NaN',
                            "points_answer" => 'NaN',
                            "level_title" => $this->plugin->txt("without_rating"),
                            "level" => 'NaN',
                        ];
                        break;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * @return list<int>
     */
    private function getChartRunData(): array
    {
        $runs = [];
        for ($i = 0; $i < count($this->obj_ids); $i++) {
            $runs[] = $i + 1 ;
        }
        return $runs;
    }

    /**
     * @return array<int|string, string>
     */
    private function getChartDSData(): array
    {
        $eval = $this->getSkillEvaluationForSvyObjects();
        $result = [];
        foreach ($eval as $key => $value) {
            if ($this->chart_scale_option === ilSurveyDataGraphsPluginGUI::VIEW_SCALE_SETTING_OPTION_LEVEL) {
                $result[$key] = implode(",", array_column($value, 'level'));
            } elseif ($this->chart_scale_option === ilSurveyDataGraphsPluginGUI::VIEW_SCALE_SETTING_OPTION_POINTS) {
                $result[$key] = implode(",", array_column($value, 'points_answer'));
            } elseif ($this->chart_scale_option === ilSurveyDataGraphsPluginGUI::VIEW_SCALE_SETTING_OPTION_POINTS_TOTAL) {
                $result[$key] = implode(",", array_column($value, 'points'));
            }
        }
        return $result;
    }

    /**
     * Computed once per element; the presentation reads it several times.
     *
     * @return array<string, mixed>
     */
    public function getChartData(): array
    {
        if ($this->chart_data !== null) {
            return $this->chart_data;
        }
        $this->chart_data = [
            "chart_title" => $this->chart_title,
            "chart_type" => count($this->obj_ids) === 1 ? 'bar' : 'line',
            "runs" => count($this->obj_ids) === 1 ? [""] : $this->getChartRunData(),
            "index_axis" => count($this->obj_ids) === 1 ? 'y' : 'x',
            "skl_data" => $this->configuration_valid ? $this->getChartDSData() : [],
            "color" => $this->colors,
            "hidden_data_label" => $this->chart_legend_hidden_level,
            "x_scale_title" => $this->x_scale_title,
            "y_scale_title" => $this->y_scale_title,
        ];
        return $this->chart_data;
    }

    /**
     * @return array<int|string, array{questions: int, answers: int, points: int|float}>
     */
    private function getAnswerDataForAll(): array
    {
        if ($this->answer_data_for_all !== null) {
            return $this->answer_data_for_all;
        }
        $result = [];
        foreach ($this->base_skills as $skl_id => $base_skill) {
            $result[$skl_id] = ["questions" => 0, "answers" => 0, "points" => 0];
            foreach ($this->obj_ids as $obj_id) {
                $answers = $this->data->getBaseSkillAnswers($obj_id, (int) $skl_id);
                $result[$skl_id]["questions"] += count($this->questions[$skl_id][$obj_id] ?? []);
                $result[$skl_id]["answers"] += count($answers);
                $result[$skl_id]["points"] += array_sum(array_column($answers, "points"));
            }
        }
        $this->answer_data_for_all = $result;
        return $result;
    }

    private function skillLevelImg(int $a_level): string
    {
        $path = match ($a_level) {
            1 => "Customizing/global/plugins/Services/COPage/PageComponent/SurveyDataGraphs/images/skl_gap_red.png",
            2 => "Customizing/global/plugins/Services/COPage/PageComponent/SurveyDataGraphs/images/skl_gap_yellow.png",
            3 => "Customizing/global/plugins/Services/COPage/PageComponent/SurveyDataGraphs/images/skl_gap_green.png",
            default => "",
        };

        return '<img style="width: 100px; float: left;" src="' . $path . '">';
    }

    /**
     * @return array<int|string, array<int|string, float|int>>
     */
    private function getDynThresholdsForAll(): array
    {
        $new_base_skill_thresholds = [];
        $count = $this->getAnswerDataForAll();

        foreach ($this->sum_thresholds as $skl_id => $base_skill) {
            $count_q = $count[$skl_id]['questions'] ?? 0;
            $count_a = $count[$skl_id]['answers'] ?? 0;

            foreach ($base_skill as $lvl_id => $base_skill_threshold) {
                if ($count_a >= 1 && $count_q >= 1) {
                    $new_base_skill_thresholds[$skl_id][$lvl_id] = ($base_skill_threshold / $count_q) * $count_a;
                } else {
                    $new_base_skill_thresholds[$skl_id][$lvl_id] = 0;
                }
            }
        }

        return $new_base_skill_thresholds;
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    private function getLevel(): array
    {
        if ($this->level !== null) {
            return $this->level;
        }
        $svy_answer_values = $this->getAnswerDataForAll();
        $svy_skill_thresholds = $this->getDynThresholdsForAll();
        $result = [];

        foreach ($svy_skill_thresholds as $skl_id => $thresholds) {
            if (!isset($svy_answer_values[$skl_id])) {
                continue;
            }
            foreach ($thresholds as $lvl_id => $threshold) {
                $lvl_datails = $this->level_data[$skl_id][$lvl_id] ?? null;
                if ($lvl_datails === null) {
                    continue;
                }
                $points = $svy_answer_values[$skl_id]['points'];

                if ($points <= $threshold && $points != 0) {
                    $result[$skl_id] = [
                        "skill_id" => $skl_id,
                        "title" => $this->base_skills[$skl_id]['title'] ?? '',
                        "level_id" => $lvl_id,
                        "questions" => $svy_answer_values[$skl_id]['questions'],
                        "answers" => $svy_answer_values[$skl_id]['answers'],
                        "points" => $points,
                        "level_title" => $lvl_datails['title'],
                        "level" => $lvl_datails['nr'],
                        "description" => $lvl_datails["description"] ?? '',
                        "img" => $this->skillLevelImg(intval($lvl_datails['nr'])),
                        "resource" => $this->data->getSkillResourceRefId(intval($lvl_id)),
                    ];
                    break;
                } elseif ($points == 0) {
                    $result[$skl_id] = [
                        "skill_id" => $skl_id,
                        "title" => $this->base_skills[$skl_id]['title'] ?? '',
                        "level_id" => $lvl_id,
                        "questions" => $svy_answer_values[$skl_id]['questions'],
                        "answers" => $svy_answer_values[$skl_id]['answers'],
                        "points" => $points,
                        "level_title" => $this->plugin->txt("without_rating"),
                        "level" => 0,
                        "description" => "",
                        "img" => $this->skillLevelImg(intval($lvl_datails['nr'])),
                        "resource" => [],
                    ];
                    break;
                }
            }
        }

        $this->level = $result;
        return $result;
    }

    public function getSkillResourceLinks(int $a_ref_id): string
    {
        $f = $this->dic->ui()->factory();
        $renderer = $this->dic->ui()->renderer();

        $obj_id = ilObject::_lookupObjectId($a_ref_id);
        $target = new URI(ilLink::_getLink($a_ref_id));
        $title = ilObject::_lookupTitle($obj_id);
        $type = ilObject::_lookupType($obj_id);

        $object_icon = $f->symbol()->icon()->standard($type, $type, 'medium');

        return $renderer->render($f->link()->bulky($object_icon, $title, $target));
    }

    private function skillColorIcon(int $a_level): string
    {
        $color = match ($a_level) {
            1 => "#D5573D",
            2 => "#DEBE3B",
            3 => "#008581",
            default => "#ffffff",
        };

        return '<svg xmlns="http://www.w3.org/2000/svg" style="float: left" width="25" height="25" viewBox="0 0 30 30"> '
            . ' <circle cx="12.5" cy="12.5" r="10" stroke="black" stroke-width="0.2" fill="' . $color . '" /> </svg>';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sklEvalTblData(): array
    {
        $levels = $this->getLevel();
        $skill_list = [];

        foreach ($levels as $level) {

            $resource_link_list = [];

            foreach ($level['resource'] as $r_link) {
                $resource_link_list[] = ['link' => $r_link['rep_ref_id']];
            }
            $proportion = $level['questions'] > 0
                ? round(($level['answers'] / $level['questions']) * 100, 2)
                : 0;
            $skill_list[] = [
                'img' => $this->skillLevelImg((int) $level['level']),
                'img2' => $this->skillColorIcon((int) $level['level']),
                'skill_title' => $level['title'],
                'description' => $level['description'],
                'level' => $level['level'],
                'level_title' => $level['level_title'],
                'links' => $resource_link_list,
                'stats' => array(
                    'questions' => $level['questions'],
                    'answered' => $level['answers'],
                    'skipped' => $level['questions'] - $level['answers'],
                    'points_total' => $level['points'],
                    'proportion' => $proportion . '%'
                )
            ];
        }
        return $skill_list;
    }

    /**
     * @return array<string, callable>
     */
    public function environment(): array
    {

        $skill_result = function ($links) {
            $result = "";
            if (empty($links)) {
                $result = htmlspecialchars($this->plugin->txt("no_learning_material"), ENT_QUOTES, 'UTF-8');
            } else {
                foreach ($links as $link) {
                    $result .= '<div style="padding-left: 45px">' . $this->getSkillResourceLinks($link['link']) . '</div>';
                }
            }
            return $result;
        };

        return  array('skill_result' => $skill_result,);
    }

    public function getMAXQuestValue(): int
    {
        $result = [0];
        foreach ($this->obj_ids as $obj_id) {
            $result[] = $this->data->getMAXQuestionValue($obj_id);
        }
        return max($result);
    }

    /**
     * A competence without a determinable level counts with zero answers
     * instead of failing on the missing index.
     */
    public function getMAXQuestTotalValue(): int
    {
        $levels = $this->getLevel();
        $max_quest_value = $this->getMAXQuestValue();
        $result = [0];
        foreach ($this->base_skills as $skl_id => $base_skill) {
            $result[] = (int) ($levels[$base_skill['skill_id'] ?? $skl_id]['answers'] ?? 0) * $max_quest_value;
        }
        return max($result);
    }

    public function getMAXQuestLevel(): int
    {
        return $this->max_level_value;
    }
}
