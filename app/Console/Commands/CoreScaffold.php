<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CoreScaffold extends Command
{
     /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'core:scaffold {name : Class (singular), e.g User} {columns* :  Columns with type, e.g name:string telephone:string}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create CRUD operations';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $name = $this->argument('name');

        $columns = array();
        foreach ($this->argument('columns') as $column) {
            $key_value = explode(":", $column);
            $columns[$key_value[0]] = $key_value[1];
        }

       $this->routes($name);


        // creating Admin Controller folder if not exist
        mkdir(app_path("/Http/Controllers/Admin/"), 0755, true);

        $this->migration($name, $columns);
        $this->model($name, $columns);
        $this->controller($name, $columns);
        
        $this->view($name, $columns);
    }

    protected function getStub($type)
    {
        return file_get_contents(resource_path("stubs/$type.stub"));
    }

    protected function getViewStub($type)
    {
        return file_get_contents(resource_path("stubs/views/$type.stub"));
    }

    protected function routes($name) {
        $path = base_path('routes/web.php');
        $content = file_get_contents($path);
        $resource_route_string = "Route::resource('/admin/" . strtolower(str_plural($name)) . "', '" . $name . "Controller');";

        if (strpos($content, $resource_route_string) === false) {
           file_put_contents($path, str_replace("<?php", "<?php ". PHP_EOL . "$resource_route_string ", $content));
        }
    }

    protected function migration($name, $columns) {


        $this->call('make:migration', [
            'name' => 'create_' . strtolower(str_plural($name)) . '_table'
        ]);


        $data = "\$table->bigIncrements('id');" . PHP_EOL;
        foreach ($columns as $key => $value) {
            $data .= "\$table->$value('$key');" . PHP_EOL;
        }

        $files = scandir(database_path('/migrations/'));

        $foundFile = false;
        foreach ($files as $filename) {
            # code...
        
            if (strpos($filename, 'create_' . strtolower(str_plural($name)) . '_table') !== false) {
                $foundFile = true;
                break;
            }
        }
        if ($foundFile) { 
               $includeColumns = str_replace(
                [
                    "\$table->bigIncrements('id');"
                ],
                [
                    $data
                ],
                file_get_contents(database_path("/migrations/$filename"))
            );
            file_put_contents(database_path("/migrations/$filename"), $includeColumns);
            $this->info("Arquivo de migração gerado com sucesso.");
        } else {
            $this->error("Não encontrou o arquivo de migração");
        }
    }
    protected function controller($name, $columns)
    {

        // Store Setting Columns
        $settingColumns = "\$" .strtolower($name) ." = new " . $name . "([";

        foreach ($columns as $key => $value) {
            $settingColumns .= "'$key' => \$request->get('$key'), " . PHP_EOL;
        }

        $settingColumns = rtrim($settingColumns, ",") . "])";

        // Edit Setting Columns
        $editSettingColumns = "";

        foreach ($columns as $key => $value) {
            $editSettingColumns .= "\$" . strtolower($name) . "->$key =  \$request->get('$key');" . PHP_EOL;
        }

        $controllerTemplate = str_replace(
            [
                '{{modelName}}',
                '{{modelNamePluralLowerCase}}',
                '{{modelNameSingularLowerCase}}',
                '{{settingColumns}}',
                '{{editSettingColumns}}'
            ],
            [
                $name,
                strtolower(str_plural($name)),
                strtolower($name),
                $settingColumns,
                $editSettingColumns
            ],
            $this->getStub('AdminController')
        );

        file_put_contents(app_path("/Http/Controllers/Admin/{$name}Controller.php"), $controllerTemplate);
    }

    protected function model($name, $columns)
    {

        

        $fillableColumns = "protected \$fillable = ['" .  implode("','", array_keys($columns)). "'];";




        $modelTemplate = str_replace(
            ['{{modelName}}', '{{columns}}'],
            [$name, $fillableColumns],
            $this->getStub('Model')
        );

        file_put_contents(app_path("/{$name}.php"), $modelTemplate);
    }

    protected function view($name, $columns)
    {

        $fieldColumns = "";
        foreach ($columns as $key => $value) {
            $fieldColumns .= '<div class="form-group">
              @csrf
              <label for="name">{{ __("model_' . strtolower($name) . '.' . $key. '") }}:</label>
              <input type="text" class="form-control" name="' . $key . '" value="{{ $' . strtolower($name) .'->' . $key . ' }}"/>
            </div>' . PHP_EOL;
        }
        

        //Create
        $viewCreateTemplate = str_replace(
            [
                '{{modelName}}',
                '{{modelNamePluralLowerCase}}',
                '{{modelNameSingularLowerCase}}',
                '{{fieldColumns}}'
            ],
            [
                $name,
                strtolower(str_plural($name)),
                strtolower($name),
                $fieldColumns
            ],
            $this->getViewStub('create')
        );

        if (!file_exists(resource_path("views/admin/" . strtolower(str_plural($name))))) {
            mkdir(resource_path("views/admin/" . strtolower(str_plural($name))), 0777, true);
        }

        file_put_contents(resource_path("views/admin/" . strtolower(str_plural($name)). "/create.blade.php"), $viewCreateTemplate);

        //Index

        $headerColumns = "";
        foreach ($columns as $key => $value) {
            $headerColumns .= "<td><b>{{ __('model_" . strtolower($name) . ".$key') }}</b></td>" . PHP_EOL;
        }

        $bodyColumns = "";
        foreach ($columns as $key => $value) {
            $bodyColumns .= "<td>{{\$" . strtolower($name) . "->$key}}</td>" . PHP_EOL;
        }
         

        $viewIndexTemplate = str_replace(
            [
                '{{modelName}}',
                '{{modelNamePlural}}',
                '{{modelNamePluralLowerCase}}',
                '{{modelNameSingularLowerCase}}',
                '{{headerColumns}}',
                '{{bodyColumns}}'
            ],
            [
                $name,
                str_plural($name),
                strtolower(str_plural($name)),
                strtolower($name),
                $headerColumns,
                $bodyColumns
            ],
            $this->getViewStub('index')
        );

        if (!file_exists(resource_path("views/admin/" . strtolower(str_plural($name))))) {
            mkdir(resource_path("views/admin/" . strtolower(str_plural($name))), 0777, true);
        }

        file_put_contents(resource_path("views/admin/" . strtolower(str_plural($name)). "/index.blade.php"), $viewIndexTemplate);

        //Index
        $viewEditTemplate = str_replace(
            [
                '{{modelName}}',
                '{{modelNamePlural}}',
                '{{modelNamePluralLowerCase}}',
                '{{modelNameSingularLowerCase}}',
                '{{fieldColumns}}'
            ],
            [
                $name,
                str_plural($name),
                strtolower(str_plural($name)),
                strtolower($name),
                $fieldColumns
            ],
            $this->getViewStub('edit')
        );

        if (!file_exists(resource_path("views/admin/" . strtolower(str_plural($name))))) {
            mkdir(resource_path("views/admin/" . strtolower(str_plural($name))), 0777, true);
        }

        file_put_contents(resource_path("views/admin/" . strtolower(str_plural($name)). "/edit.blade.php"), $viewEditTemplate);
    }
}
