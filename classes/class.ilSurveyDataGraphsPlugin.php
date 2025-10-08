<?php

class ilSurveyDataGraphsPlugin extends ilPageComponentPlugin
{

    public function getPluginName(): string
    {
        return "SurveyDataGraphs";
    }
    
    public function isValidParentType(string $a_type): bool
    {
        return true;
    }
}