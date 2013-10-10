<?php

namespace Way\Generators\Generators;

class SeedGenerator extends Generator {

    /**
     * Fetch the compiled template for a seed
     *
     * @param  string $template Path to template
     * @param  string $className
     * @return string Compiled template
     */
    protected function getTemplate($template, $className)
    {
        $this->template = $this->file->get($template);
        $models = strtolower(str_replace('TableSeeder', '', $className));
        $modelName = Illuminate\Support\Pluralizer::singular(str_replace('TableSeeder', '', $className));
		
		$fields = \DB::select("DESCRIBE $models");
		$table = array();
		foreach($fields as $field) {
			if ($field->Key !== 'PRI') $table[] = "\t\t\t\t'$field->Field' => '$field->Default'";
		}
		$tableFields = implode(",\n", $table);

        $this->template = str_replace('{{className}}', $className, $this->template);
        $this->template = str_replace('{{modelName}}', $modelName, $this->template);
        $this->template = str_replace('{{tableFields}}', trim($tableFields), $this->template);

        return str_replace('{{models}}', $models, $this->template);
    }

    /**
    * Updates the DatabaseSeeder file's run method to
    * call this new seed class
    * @return void
    */
    public function updateDatabaseSeederRunMethod($className)
    {
        $databaseSeederPath = app_path() . '/database/seeds/DatabaseSeeder.php';

        $content = $this->file->get($databaseSeederPath);

        if ( ! strpos($content, "\$this->call('{$className}');"))
        {
            $content = preg_replace("/(run\(\).+?)}/us", "$1\t\$this->call('{$className}');\n\t}", $content);
            return $this->file->put($databaseSeederPath, $content);
        }

        return false;
    }

}