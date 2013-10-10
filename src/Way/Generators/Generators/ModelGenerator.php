<?php

namespace Way\Generators\Generators;

use Illuminate\Support\Pluralizer;
use Illuminate\Support\Str;
use Config;

class ModelGenerator extends Generator {

    /**
     * Fetch the compiled template for a model
     *
     * @param  string $template Path to template
     * @param  string $className
     * @return string Compiled template
     */
    protected function getTemplate($template, $className)
    {
        $this->template = $this->file->get($template);
        $models = Pluralizer::plural(strtolower($className));

        if ($this->needsScaffolding($template))
        {
            $this->template = $this->getScaffoldedModel($className);
        }

		$fields = $this->describeTable($models);
		$rules = array();
		$columnList = array();
		foreach($fields as $field) {
			$rules[] = "\t\t'{$field['name']}' => 'required'";
		}
		$rules = implode(",\n", $rules);

		foreach($fields as $field) {
			$columnList[] = str_pad("\t'{$field['name']}', ", 24) . "// type: {$field['type']}, default: {$field['default']}";
		}
		$columnList = implode("\n", $columnList);

		$fks = $this->getForeignKeys($models);
		$tmpl = $this->file->get(dirname(dirname(__FILE__)).'/Generators/templates/scaffold/relations.txt');
		$code = array();
		foreach($fks as $ref) {
			$code[] = str_replace(['{{name}}', '{{model}}'], [$ref['name'], $ref['model']],  $tmpl);
		}
		$relations = implode("\n\n", $code);

        $this->template = str_replace('{{className}}', $className, $this->template);
        $this->template = str_replace('{{rules}}', trim($rules), $this->template);
        $this->template = str_replace('{{columnList}}', $columnList, $this->template);
        return str_replace('{{relations}}', $relations, $this->template);
    }

    /**
     * Get template for a scaffold
     *
     * @param  string $template Path to template
     * @param  string $name
     * @return string
     */
    protected function getScaffoldedModel($className)
    {
        if (! $fields = $this->cache->getFields())
        {
            return str_replace('{{rules}}', '', $this->template);
        }

        $rules = array_map(function($field) {
            return "'$field' => 'required'";
        }, array_keys($fields));

        return str_replace('{{rules}}', PHP_EOL."\t\t".implode(','.PHP_EOL."\t\t", $rules) . PHP_EOL."\t", $this->template);
    }

	/**
	 * Get foreign keys
	 *
     * @param  string $table
	 * @return array
	 */
	protected function getForeignKeys($table) {
		$config = Config::get('database.default');
		$dbName = Config::get("database.connections.$config.database");

		$fks = \DB::select("SELECT table_schema, referenced_table_name FROM information_schema.KEY_COLUMN_USAGE WHERE table_name = '$table' AND table_schema = '$dbName'");

		$foreignKeys = array();
		foreach($fks as $fk) {
			if (!empty($fk->referenced_table_name)) {
				$ref = [
					'name' => $fk->referenced_table_name, 
					'model' => Str::studly(Pluralizer::singular($fk->referenced_table_name))
				];
				$foreignKeys[] = $ref;
			}
		}
		return $foreignKeys;
	}

}
