<?php

use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;

class ilSurveyDataGraphsPresentationGUI
{
    public const string SI_RESULT_ID_HEADER = "si_result_id_header";

    public const string SI_RESULT_DATE_HEADER = "si_result_date_header";

    public const string SI_RESULT_ANSWERED_HEADER = "si_result_answered_header";

    private const string JS_CHART = "/js/chart.umd.min.js";

    private const string JS_INIT = "/js/surveydatagraphs.js";

    private ilPlugin $plugin;

    private static int $id_counter = 0;

    private static bool $js_initialised = false;

    private mixed $properties;

    private ilSurveyDataGraphsSkillUserData $data;

    private Factory $factory;

    private Renderer $renderer;

    /**
     * @param array<string, string> $a_properties
     */
    public function __construct(ilPlugin $a_plugin, array $a_properties)
    {
        global $DIC;
        $this->factory = $DIC->ui()->factory();
        $this->renderer = $DIC->ui()->renderer();
        $this->data = new ilSurveyDataGraphsSkillUserData($a_properties, $a_plugin);
        $this->properties = $a_properties;
        $this->plugin = $a_plugin;
        self::$id_counter++;
    }

    private function escape(mixed $a_value): string
    {
        return htmlspecialchars((string) $a_value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * @throws ilTemplateException
     * @throws ilSystemStyleException
     */
    private function svyDetailTableTpl(): string
    {
        $data = $this->data->getResultListData();

        $tpl = new ilTemplate(
            "tpl.svy-result-list.html",
            true,
            true,
            'public/' . ilSurveyDataGraphsConstants::PLUGIN_DIRECTORY,
            ilGlobalTemplateInterface::DEFAULT_BLOCK,
            true
        );

        $tpl->setVariable("SI_RESULT_ID_HEADER", $this->escape($this->plugin->txt(self::SI_RESULT_ID_HEADER)));
        $tpl->setVariable("SI_RESULT_DATE_HEADER", $this->escape($this->plugin->txt(self::SI_RESULT_DATE_HEADER)));
        $tpl->setVariable("SI_RESULT_ANSWERED_HEADER", $this->escape($this->plugin->txt(self::SI_RESULT_ANSWERED_HEADER)));

        foreach ($data as $item) {
            if (!empty($item['date'])) {
                $tpl->setCurrentBlock("si-result-list-row");
                $tpl->setVariable("SI_RESULT_ID", $this->escape($item["id"]));
                $tpl->setVariable("SI_RESULT_DATE", $this->escape($item["date"]));
                $tpl->setVariable("SI_RESULT_ANSWERED", $this->escape(sprintf(
                    $this->plugin->txt("answered_of_questions"),
                    count($item["answers"]),
                    count($item["questions"])
                )));
                $tpl->parseCurrentBlock();
            }
        }
        return $tpl->get();
    }

    /**
     * Builds the Chart.js configuration. It reaches the browser as JSON in a
     * data attribute, so titles and labels never become JavaScript source.
     *
     * @return array<string, mixed>
     */
    private function getChartConfig(): array
    {
        $chart_data = $this->data->getChartData();
        [$x_min, $x_max, $y_min, $y_max] = $this->getAxisRange($chart_data);
        $datasets = $this->getDatasets($chart_data);

        return [
            'type' => $chart_data['chart_type'],
            'data' => [
                'labels' => $chart_data['runs'],
                'datasets' => $datasets,
            ],
            'options' => [
                'indexAxis' => $chart_data['index_axis'],
                'elements' => [
                    'bar' => ['borderWidth' => 1],
                    'line' => ['borderWidth' => 1],
                ],
                'scales' => [
                    'y' => [
                        'min' => $y_min,
                        'max' => $y_max,
                        'title' => ['display' => true, 'text' => (string) $chart_data['y_scale_title']],
                        'offset' => true,
                        'ticks' => ['stepSize' => 1],
                    ],
                    'x' => [
                        'min' => $x_min,
                        'max' => $x_max,
                        'title' => ['display' => true, 'text' => (string) $chart_data['x_scale_title']],
                        'ticks' => ['stepSize' => 1],
                    ],
                ],
                'interaction' => ['intersect' => false, 'mode' => 'point'],
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => [
                    'legend' => [
                        'display' => true,
                        'position' => 'bottom',
                        'labels' => ['color' => 'rgb(0, 0, 0)'],
                    ],
                    'title' => ['display' => true, 'text' => (string) $chart_data['chart_title']],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $a_chart_data
     * @return list<array<string, mixed>>
     */
    private function getDatasets(array $a_chart_data): array
    {
        if (!$this->data->getRequirements()) {
            return $this->getPlaceholderDatasets();
        }
        $datasets = [];
        $count = 0;
        foreach ($this->data->getBaseSkillData() as $skl_id => $skill) {
            $dataset = [
                'label' => (string) $skill['title'],
                'data' => $this->toNumbers((string) ($a_chart_data['skl_data'][$skl_id] ?? '')),
                'borderWidth' => 2,
                'hidden' => $count >= (int) $a_chart_data['hidden_data_label'],
            ];
            if (isset($a_chart_data['color'][$count])) {
                $dataset['backgroundColor'] = $a_chart_data['color'][$count];
                $dataset['borderColor'] = $a_chart_data['color'][$count];
            }
            $datasets[] = $dataset;
            $count++;
        }
        return $datasets;
    }

    /**
     * Shown while the surveys are not completed: legend only, no values.
     *
     * @return list<array<string, mixed>>
     */
    private function getPlaceholderDatasets(): array
    {
        $datasets = [];
        foreach ($this->data->getBaseSkillData() as $skill) {
            $datasets[] = [
                'label' => (string) $skill['title'],
                'data' => [],
                'backgroundColor' => '#000',
                'borderColor' => '#000',
                'borderWidth' => 2,
                'hidden' => false,
            ];
        }
        return $datasets;
    }

    /**
     * @param array<string, mixed> $a_chart_data
     * @return array{int, int, int, int} x min, x max, y min, y max
     */
    private function getAxisRange(array $a_chart_data): array
    {
        if ($a_chart_data['index_axis'] === 'x') {
            return [1, count($a_chart_data['runs']), 1, $this->getChartScaleIndex()];
        }
        return [0, $this->getChartScaleIndex(), 0, 1];
    }

    /**
     * "1,2,NaN" becomes [1, 2, null]; null leaves a gap in the chart.
     *
     * @return list<int|float|null>
     */
    private function toNumbers(string $a_csv): array
    {
        if ($a_csv === '') {
            return [];
        }
        return array_map(
            static fn(string $value): int|float|null => is_numeric($value) ? $value + 0 : null,
            explode(',', $a_csv)
        );
    }

    /**
     * @throws ilTemplateException
     * @throws JsonException
     */
    private function getChartTpl(): string
    {
        $tpl = new ilTemplate(
            "tpl.chart.html",
            true,
            true,
            'public/' . ilSurveyDataGraphsConstants::PLUGIN_DIRECTORY,
            ilGlobalTemplateInterface::DEFAULT_BLOCK,
            true
        );

        $tpl->setVariable("CHARTID", "sdg_" . self::$id_counter);
        $tpl->setVariable("CHART_ARIA_LABEL", $this->escape($this->plugin->txt("chart_aria_label")));
        $tpl->setVariable("CHART_FALLBACK", $this->escape($this->plugin->txt("chart_fallback")));
        $tpl->setVariable("CHART_CONFIG", $this->escape(json_encode($this->getChartConfig(), JSON_THROW_ON_ERROR)));

        return $tpl->get();
    }

    private function getChartScaleIndex(): int
    {
        $result = 0;
        if ($this->properties[ilSurveyDataGraphsPluginGUI::EDIT_CONF_SCALE_SETTING_OPTION] === ilSurveyDataGraphsPluginGUI::VIEW_SCALE_SETTING_OPTION_LEVEL) {
            $result = $this->data->getMAXQuestLevel();
        } elseif ($this->properties[ilSurveyDataGraphsPluginGUI::EDIT_CONF_SCALE_SETTING_OPTION] === ilSurveyDataGraphsPluginGUI::VIEW_SCALE_SETTING_OPTION_POINTS) {
            $result = $this->data->getMAXQuestValue();
        } elseif ($this->properties[ilSurveyDataGraphsPluginGUI::EDIT_CONF_SCALE_SETTING_OPTION] === ilSurveyDataGraphsPluginGUI::VIEW_SCALE_SETTING_OPTION_POINTS_TOTAL) {
            $result = $this->data->getMAXQuestTotalValue();
        }
        return $result;
    }

    /**
     * Chart.js and the init script are added once per request, however many
     * elements the page contains.
     */
    private function addJavaScript(ilGlobalTemplateInterface $a_template): void
    {
        if (self::$js_initialised) {
            return;
        }
        self::$js_initialised = true;
        $a_template->addJavaScript(ilSurveyDataGraphsConstants::PLUGIN_DIRECTORY . self::JS_CHART);
        $a_template->addJavaScript(ilSurveyDataGraphsConstants::PLUGIN_DIRECTORY . self::JS_INIT);
        $a_template->addOnLoadCode("il.SurveyDataGraphs.init();");
    }

    /**
     * @throws ilTemplateException
     * @throws ilSystemStyleException
     * @throws JsonException
     */
    public function getContentTpl(): string
    {
        global $DIC;

        $template = $DIC->ui()->mainTemplate();
        $template->addCss(ilSurveyDataGraphsConstants::PLUGIN_DIRECTORY . "/css/surveydatagraphs.css");
        $this->addJavaScript($template);

        $tpl = new ilTemplate(
            "tpl.content.html",
            true,
            true,
            'public/' . ilSurveyDataGraphsConstants::PLUGIN_DIRECTORY,
            ilGlobalTemplateInterface::DEFAULT_BLOCK,
            true
        );

        $tpl_access = new ilTemplate(
            "tpl.access-container.html",
            true,
            true,
            'public/' . ilSurveyDataGraphsConstants::PLUGIN_DIRECTORY,
            ilGlobalTemplateInterface::DEFAULT_BLOCK,
            true
        );

        if ($this->data->getRequirements()) {
            $tpl->setVariable("SKL_NAV", $this->sklEvaluationTableGUI());
        } else {
            $tpl_access->setVariable("ACCESS_ITEM", $this->escape($this->data->getChartPlaceholder()));
            $tpl->setVariable("ACCESS", $tpl_access->get());
        }

        $tpl->setVariable("TPL_CLASS", $this->escape($this->data->getTplClassname()));
        $tpl->setVariable("TPL_TITLE", $this->escape($this->data->getTplTitle()));
        $tpl->setVariable("CHART_TPL", $this->getChartTpl());
        $tpl->setVariable("SI_RESULT_LIST_TBL", $this->svyDetailTableTpl());

        return $tpl->get();
    }

    /**
     * @throws ilTemplateException
     */
    private function sklEvaluationTableGUI(): string
    {
        $view_controls = array();
        $label_level = $this->plugin->txt("level");
        $label_answered = $this->plugin->txt("answered");
        $answered_of = $this->plugin->txt("answered_of_questions");
        $recommended = $this->escape($this->plugin->txt("recommended_content"));
        $mapping_closure = function ($row, $record, $ui_factory, $environment) use ($label_level, $label_answered, $answered_of, $recommended) {
            $level_title = $this->escape($record['level_title']);
            return $row
                ->withHeadline($this->escape($record['skill_title']))
                ->withImportantFields(
                    array(
                        $label_level => $level_title . $record['img2'],
                        $label_answered => $this->escape(
                            sprintf($answered_of, $record['stats']['answered'], $record['stats']['questions'])
                            . " - " . $record['stats']['proportion']
                        ),
                    )
                )
                ->withContent(
                    $ui_factory->listing()->descriptive(
                        array(
                            $level_title => $record['img'] . $this->escape($record['description']),
                        )
                    )
                )
                ->withFurtherFieldsHeadline("<strong>" . $recommended . "</strong>")
                ->withFurtherFields(array("" => $environment['skill_result']($record['links'])));
        };

        $table = $this->factory->table()->presentation(
            "",
            $view_controls,
            $mapping_closure
        )->withEnvironment($this->data->environment());
        $data = $this->data->sklEvalTblData();

        $panel = $this->factory->panel()->standard(
            $this->plugin->txt("individual_levels"),
            $this->factory->legacy()->content(
                "<div style='overflow-y: scroll; height:400px;'>" .
                $this->renderer->render($table->withData($data)) .
                "</div>"
            )
        );

        return $this->renderer->render($panel);
    }
}
