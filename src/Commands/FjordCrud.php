<?php

namespace AwStudio\Fjord\Commands;

use AwStudio\Fjord\Filesystem\StubBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class FjordCrud extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fjord:crud';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This wizard will generate all the files needed for a new crud module';

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
        $modelName = ucfirst(str_singular($this->ask('enter the model name')));
        $m = $this->choice('does the model have media?', ['y', 'n'], 0) == 'y' ? true : false;
        $s = $this->choice('does the model have a slug?', ['y', 'n'], 0) == 'y' ? true : false;
        $t = $this->choice('does the model need to be translated?', ['y', 'n'], 0) == 'y' ? true : false;
        $so = $this->choice('does the model need to be sortable?', ['y', 'n'], 0) == 'y' ? true : false;

        $this->makeModel($modelName, $m, $s, $t);
        $this->makeMigration($modelName, $s, $t, $so);
        $this->makeController($modelName);
        $this->makeConfig($modelName);

        $this->info("\n----- finished -----\n");
        $this->info('1) edit the generated migration and migrate');
        $this->info('2) set the fillable fields in your model(s)');
        $this->info('3) make your model editable by adding it to the config/fjord-crud.php');
        $this->info('4) add a navigation entry to your config/fjord-navigation.php');

        $this->info("\nif your navigation entry doesn't appear, consider clearing your cache\n");
    }

    private function makeModel($modelName, $m, $s, $t)
    {
        $model = app_path('Models/'.$modelName.'.php');

        $implements = [];
        $uses = [];
        $appends = [];

        if(file_exists($model)) {
            $this->error('model already exists');
        }

        $builder = new StubBuilder(fjord_path('stubs/CrudModel.stub'));

        $builder->withClassname($modelName);

        // getRoute routename
        $builder->withRoutename(Str::snake(Str::plural($modelName)));

        // model has media
        if($m) {
            $builder->withTraits("use Spatie\MediaLibrary\Models\Media;");
            $builder->withTraits("use Spatie\MediaLibrary\HasMedia\HasMedia;");
            $builder->withTraits("use Spatie\MediaLibrary\HasMedia\HasMediaTrait;");

            $attributeContents = file_get_contents(fjord_path('stubs/CrudModelMediaAttribute.stub'));
            $builder->withGetAttributes($attributeContents);

            $implements []= 'HasMedia';
            $uses []= 'HasMediaTrait';
            $appends []= 'image';
        }

        // model has slug
        if($s){
            // if is not translated
            if(!$t){
                $builder->withTraits("use Cviebrock\EloquentSluggable\Sluggable;");

                $sluggableContents = file_get_contents(fjord_path('stubs/CrudModelSluggable.stub'));
                $builder->withSluggable($sluggableContents);

                $uses []= 'Sluggable';
            }
        }

        // model is translatable
        if($t){
            $builder->withTraits("use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;");
            $builder->withTraits("use Astrotomic\Translatable\Translatable;");
            $builder->withVars('public $translatedAttributes'." = ['title', 'text'];");

            $attributeContents = file_get_contents(fjord_path('stubs/CrudModelTranslationAttribute.stub'));
            $builder->withGetAttributes($attributeContents);

            $implements []= 'TranslatableContract';
            $uses []= 'Translatable';
            $appends []= 'translation';

            $this->makeTranslationModel($modelName, $s);
        }

        if($implements) {
            $builder->withImplement('implements ' . implode(', ', $implements));
        }

        if($uses) {
            $builder->withUses('use ' . implode(', ', $uses) . ';');
        }

        if($appends) {
            $builder->withVars("\tprotected \$appends = ['" . implode("', '", $appends) . "'];");
        }

        $builder->create($model);

        $this->info('model created');
    }

    private function makeTranslationModel($modelName, $s)
    {
        $model = app_path('Models/Translations/'.$modelName.'Translation.php');

        if (!file_exists($model) ) {
            $fileContents = file_get_contents(__DIR__.'/../../stubs/CrudTranslationModel.stub');

            $fileContents = str_replace('DummyClassname', $modelName.'Translation', $fileContents);

            // if the model is sluggable, add sluggable trait
            if($s){
                $fileContents = str_replace('DummyTraits', "use Cviebrock\EloquentSluggable\Sluggable;\nDummyTraits", $fileContents);

                $sluggableContents = file_get_contents(__DIR__.'/../../stubs/CrudModelSluggable.stub');
                $fileContents = str_replace('DummySluggable', $sluggableContents, $fileContents);

                $uses = ['Sluggable'];
                $fileContents = $this->makeUses($uses, $fileContents);
            }

            // remove placeholders
            $fileContents = $this->cleanUp($fileContents);

            if(!\File::exists('app/Models/Translations')){
                \File::makeDirectory('app/Models/Translations');
            }
            if(\File::put($model, $fileContents)){
                $this->info('model created');
            }
        }else{
            $this->error('translation-model already exists');
        }
    }

    private function makeMigration($modelName, $s, $t, $so)
    {
        $tableName = Str::snake(Str::plural($modelName));
        $translationTableName = Str::singular($tableName) . '_translations';

        $fileContents = file_get_contents(__DIR__.'/../../stubs/CrudMigration.stub');

        // model is translatable
        if($t){
            $translationContents = file_get_contents(__DIR__.'/../../stubs/CrudMigrationTranslation.stub');
            $fileContents = str_replace('DummyTranslation', $translationContents, $fileContents);
            $fileContents = str_replace('DummyDownTranslation', "Schema::dropIfExists('DummyTranslationTablename');", $fileContents);
            $fileContents = str_replace('DummyTranslationTablename', $translationTableName , $fileContents);
            $fileContents = str_replace('DummyForeignId', Str::singular($tableName) . '_id', $fileContents);
        }else{
            $fileContents = str_replace('DummyTranslation', '', $fileContents);
            $fileContents = str_replace('DummyTranslationDown', '', $fileContents);
        }

        // model has slug
        if($s){
            $fileContents = str_replace('DummySlug', '$table->string'."('slug')->nullable();", $fileContents);
        }else{
            $fileContents = str_replace('DummySlug', '', $fileContents);
        }

        if($so) {
            $fileContents = str_replace('DummySortable', '$table->unsignedInteger'."('order_column')->nullable();", $fileContents);
        }

        $fileContents = str_replace('DummyClassname', "Create".ucfirst(str_plural($modelName))."Table", $fileContents);
        $fileContents = str_replace('DummyTablename', $tableName, $fileContents);



        $timestamp = str_replace(' ', '_', str_replace('-', '_', str_replace(':', '', now())));
        if(\File::put('database/migrations/'.$timestamp.'_create_'. $tableName .'_table.php', $fileContents)){
            $this->info('migration created');
        }
    }

    private function makeController($modelName)
    {
        $controller = app_path('Http/Controllers/Fjord/'.$modelName.'Controller.php');

        $fileContents = file_get_contents(__DIR__.'/../../stubs/CrudController.stub');

        $fileContents = str_replace('DummyClassname', $modelName . 'Controller', $fileContents);
        $fileContents = str_replace('DummyModelName', $modelName, $fileContents);

        if(!\File::exists('app/Http/Controllers/Fjord')){
            \File::makeDirectory('app/Http/Controllers/Fjord');
        }
        if(\File::put($controller, $fileContents)){
            $this->info('controller created');
        }
    }

    private function makeConfig($modelName)
    {
        $tableName = Str::snake(Str::plural($modelName));
        $config = fjord_resource_path('crud/'.$tableName.'.php');

        $fileContents = file_get_contents(__DIR__.'/../../stubs/CrudConfig.stub');
        $fileContents = str_replace('DummyClassname', $modelName, $fileContents);

        if(! is_dir(fjord_resource_path('crud'))) {
            \File::makeDirectory(fjord_resource_path('crud'));
        }

        if(\File::put($config, $fileContents)){
            $this->info('config created');
        }
    }

    private function cleanUp($fileContents)
    {
        $fileContents = str_replace('DummyTraits', '', $fileContents);
        $fileContents = str_replace('DummyUses', '', $fileContents);
        $fileContents = str_replace('DummyVars', '', $fileContents);
        $fileContents = str_replace('DummySluggable', '', $fileContents);
        $fileContents = str_replace('DummyGetAttributes', '', $fileContents);
        $fileContents = str_replace('DummyImplement', '', $fileContents);

        return $fileContents;
    }

    private function makeImplements($implements, $fileContents)
    {
        // model implements…
        if(count($implements) > 0){
            $delimiter = '';
            $str = 'implements ';
            foreach ($implements as $imp) {
                $str .= $delimiter . $imp;
                $delimiter = ', ';
            }
            $fileContents = str_replace('DummyImplement', $str, $fileContents);
        }

        return $fileContents;
    }

    private function makeUses($uses, $fileContents)
    {
        // model uses traits:
        if(count($uses) > 0){
            $delimiter = '';
            $str = 'use ';
            foreach ($uses as $use) {
                $str .= $delimiter . $use;
                $delimiter = ', ';
            }
            $fileContents = str_replace('DummyUses', $str.';', $fileContents);
        }

        return $fileContents;
    }

    private function makeAppends($appends, $fileContents)
    {
        // model appends:
        if(count($appends) > 0){
            $delimiter = '';
            $str = 'protected $appends = [';
            foreach ($appends as $append) {
                $str .= $delimiter . "'" . $append . "'";
                $delimiter = ', ';
            }
            $fileContents = str_replace('DummyVars', $str.'];', $fileContents);
        }

        return $fileContents;
    }


}
