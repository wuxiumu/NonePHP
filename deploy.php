<?php
namespace Deployer;

use DateTime;

require 'recipe/common.php';

date_default_timezone_set('Asia/Shanghai');
// Project name
set('application', 'NonePHP');

// Project repository
set('repository', 'https://github.com/varobjs/NonePHP.git');

// [Optional] Allocate tty for git clone. Default value is false.
set('git_tty', true);

// Shared files/dirs between deploys
set('shared_files', []);
set('shared_dirs', []);

// Writable dirs by web server
set('writable_dirs', []);
set('allow_anonymous_stats', false);

// Hosts
host('localhost')
    ->port('80')
    ->user('root')
    ->set('deploy_path', '/code/www/webroot/deploy/{{application}}');


// Tasks
desc('Deploy your project');
task('deploy', [
    'deploy:info',
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:shared',
    'deploy:writable',
    'deploy:clear_paths',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
    'success'
]);

task('deploy:release', static function () {
    $guard = 42;
    do {
        $time = microtime();
        [$usec, $sec] = explode(' ', $time);
        $date = DateTime::createFromFormat('U.u', $sec . '.' . ($usec * 1e6));
        $releaseName = $date->format('YmdHis');
        $releasePath = '/code/www/webroot/deploy/NonePHP/releases/' . $releaseName;
    } while (is_dir($releasePath) && --$guard);
    run("mkdir $releasePath");

    run('cd {{deploy_path}} && if [ -h release ]; then rm release; fi');

    run("ln -s $releasePath {{deploy_path}}/release");
})->desc('Prepare release');

// [Optional] If deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');
