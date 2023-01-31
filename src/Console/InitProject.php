<?php

namespace ZDSLab\Init\Console;

// Models 
use App\Models\{User,};
use Illuminate\Support\Arr;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Filesystem\Filesystem;

use Illuminate\Database\Seeder;
use TCG\Voyager\Models\DataRow;
use TCG\Voyager\Models\DataType;
use TCG\Voyager\Models\Permission;
use TCG\Voyager\Models\Menu;
use TCG\Voyager\Models\MenuItem;

class InitProject extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'project:init';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize project';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->info('Installing ZDSLab InitPackage...');

        $this->call('voyager:install');
        
        $this->info('Set Application route to ZDS routes into routes/web.php');
        
        copy(__DIR__.'/../../stubs/default/routes/web.php', base_path('routes/web.php'));

        $this->info('Pubish ZDS Controllers and helpers');

        $this->putFileInFolder(
            __DIR__.'/../../stubs/default/Http/Controllers', 
            app_path('Http/Controllers')
        );

        $this->initBread();
        // $this->putFileInFolder(
        //     __DIR__.'/../../stubs/default/Http/Helpers', 
        //     app_path('Http/Controllers')
        // );
            
        // Create new user with admin privilege
        $create = $this->ask('Do you want to create a user ? (Yes => 1 | No => 0)');
        $this->createUser(boolval($create));
        
        // Set login images
        $loginDir = "public/login-assets";
        // Create directory if not exists
        if (
            !is_dir( base_path($loginDir) )
        ) mkdir($loginDir, 0775, true);

        $this->putFileInFolder(
            __DIR__.'/../../publishable/login-assets', base_path($loginDir)
        );

        $this->makeFirstPage();
        $this->info('End of the installation process');

    }

    private function configExists($fileName)
    {
        return File::exists(config_path($fileName));
    }

    private function shouldOverwriteConfig()
    {
        return $this->confirm(
            'Config file already exists. Do you want to overwrite it?',
            false
        );
    }

    private function publishConfiguration($forcePublish = false)
    {
        $params = [
            '--provider' => "ZDSLab\Init\InitServiceProvider",
            '--tag' => "config"
        ];

        if ($forcePublish === true) {
            $params['--force'] = true;
        }

       $this->call('vendor:publish', $params);
    }

    private function initBread() {
        $data = json_decode(file_get_contents(__DIR__."/../../data.json"), true);

        $withoutMenus = ['page_sections'];

        $menu = Menu::where('name', 'admin')->firstOrFail();

        $parentMenuItem = MenuItem::firstOrNew([
            'menu_id' => $menu->id,
            'title'   => "Portail",
            'url'     => '',
        ]);

        if (!$parentMenuItem->exists) {
            $parentMenuItem->fill([
                'target'     => '_self',
                'icon_class' => 'voyager-activity',
                'color'      => null,
                'parent_id'  => null,
                'order'      => 2,
            ])->save();
        }

        // Guess sub menu_items order start
        $orders = $parentMenuItem->children->pluck('order')->toArray();
        $subItemOrder = !empty($orders) ? max($orders) : 1;

        // save `data_types` locate in `data.json`  
        foreach ($data['voyager']['data_types'] as $current_data_type) {
            // `current_data_type` content actual dataType value

            // check if `data_type` exists else -> create new
            $dataType = $this->dataType('slug', $current_data_type['slug']);

            if (!$dataType->exists) {

                $fill_data = array(); // data to save
                // dd($current_data_type);
                foreach ($current_data_type as $row => $row_value) {
                    if ($row != 'data_rows') // if actual `row` is not `data_rows`, save that as a field in `data_type`
                        $fill_data[$row] = $row_value;
                }

                $dataType->fill($fill_data)->save();
            }

            // if `data_rows` of this `data_types` is not empty save them
            if ($current_data_type['data_rows']) {
                foreach ($current_data_type['data_rows'] as $current_data_row) {

                    $dataRow = $this->dataRow($dataType, $current_data_row['field']);
                    if (!$dataRow->exists) {

                        $fill_data = array(); // data to save
                        foreach ($current_data_row as $drow => $drow_value) {
                            if ($drow != 'field') // if actual `row` is not `field`, save that as a field in `data_type`
                                $fill_data[$drow] = $drow_value;
                            if ($drow == 'details') {
                                $fill_data[$drow] = json_decode($drow_value, true);
                                // dd($fill_data[$drow]);
                            }
                        }
                        $dataRow->fill($fill_data)->save();
                    }
                }
            }

            if( !in_array($current_data_type['slug'], $withoutMenus) ) {

                $menuItem = MenuItem::firstOrNew([
                    'menu_id' => $menu->id,
                    'title'   => ucfirst($current_data_type['display_name_plural']),
                    'url'     => null,
                    'route'   => 'voyager.' . $current_data_type['slug'] . '.index',
                ]);
        
                if (!$menuItem->exists) {
                    $menuItem->fill([
                        'target'     => '_self',
                        'icon_class' => $current_data_type['icon'],
                        'color'      => null,
                        'parent_id'  => $parentMenuItem->id,
                        'order'      => $subItemOrder++,
                    ])->save();
                }
            }

            Permission::generateFor($current_data_type['name']);
        }

    }

    public function makeFirstPage()
    {
        $tid = DB::table('templates')
            ->insertGetId([
                'name' => "Master",
                'vue' => "master"
            ]);
        $sid = DB::table('sections')
            ->insertGetId([
                'name' => "About",
                'vue' => "about",
                "title" => "A propos de Woody Builder"
            ]);

        $pid = DB::table('pages')
            ->insertGetId([
                'name' => "Home",
                'slug' => "home",
                "title" => "Woody Builder",
                "template_id" => $tid
            ]);
        DB::table('page_sections')
            ->insert(
                [
                    'page_id' => $pid,
                    'section_id' => $sid,
                    'rang' => 1
                ]
            );
    }

    protected function createUser($create = false)
    {
        if ($create) {
            $email           = $this->ask('Enter your e-mail');
            $name            = $this->ask('Enter your name');
            $password        = $this->secret('Password');
            $confirmPassword = $this->secret('Password confirmation');

            if ($password == $confirmPassword) {
                $newUser = new User;
                $newUser->name = $name;
                $newUser->role_id = 1;
                $newUser->email = $email;
                $newUser->password = Hash::make($password); //bcrypt($password) ;
                $newUser->save();
                $this->info('Successfully created user');
            } else {
                $this->info('The entered passwords do not match.');
            }
        }
    }

    /**
     * [dataType description].
     *
     * @param [type] $field [description]
     * @param [type] $for   [description]
     *
     * @return [type] [description]
     */
    protected function dataType($field, $for)
    {
        return DataType::firstOrNew([$field => $for]);
    }

    /**
     * [dataRow description].
     *
     * @param [type] $type  [description]
     * @param [type] $field [description]
     *
     * @return [type] [description]
     */
    protected function dataRow($type, $field)
    {
        return DataRow::firstOrNew([
            'data_type_id' => $type->id,
            'field'        => $field,
        ]);
    }

    private function putFileInFolder($source, $destination)
    {
        $scan = scandir($source);
        foreach($scan as $file) {
            if (!is_dir("$source/$file")) {
                copy("$source/$file", "$destination/$file");
            }
        }

        // foreach ($files as $name => $content) :
        //     $chemin =  $path . $name;
        //     $path_info = pathinfo($chemin);
        //     $dir = $path_info['dirname'];
        //     if (!file_exists($dir)) :
        //         mkdir($dir, 0775, true);
        //     endif;
        //     file_put_contents($chemin, trim($content));
        // endforeach;
    }

}
