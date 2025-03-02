<?php

namespace Drutiny\Target\VCS;

trait GitTrait
{
    protected bool $gitIsLocal;
    protected string $gitRemote;

    protected function setLocation($location)
    {
      // Check if its a local git repository.
        $this->gitIsLocal = is_dir($location) && is_dir("$location/.git");

      // Treat non directories as git remotes.
        if (!$this->gitIsLocal) {
            $this->gitRemote = $location;
        } else {
            $this->gitRemote = $this->getService('local')
             ->run("cd $location && git remote", function ($output) {
                return (string) @array_pop(@explode(PHP_EOL, trim($output)));
             });
        }

        $this['vcs.git.location'] = $location;
        $this['vcs.git.remote'] = $this->gitRemote;
        $this['vcs.git.isLocal'] = $this->gitIsLocal;
    }

  /**
   * Ensure the git repository is available locally.
   */
    protected function useLocal($branch = null, $max_depth = false)
    {
        if ($this->gitIsLocal) {
            return $this;
        }

        $location = getcwd() . "/.drutinyVcsGit";
        $cleanup = function () use ($location) {
            if (file_exists($location)) {
                exec('rm -rf ' . $location);
            }
        };
      // Ensure repository does not hang around afterward.
        register_shutdown_function($cleanup);
      // Ensure previous clones are not present.
        $cleanup();


        $cmd = "git clone";
        if (isset($branch)) {
            $cmd .= " --branch=$branch";
        }
        if (isset($branch)) {
            $cmd .= " --depth=$max_depth";
        }
        $cmd .= " {$this->gitRemote}";

        $this->getService('local')->run($cmd);

        $this->gitIsLocal = true;
        $this['vcs.git.location'] = $location;

        return $this;
    }
}
