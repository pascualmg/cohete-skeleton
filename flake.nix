{
  description = "Cohete Skeleton - Async PHP HTTP server on ReactPHP";

  inputs = {
    nixpkgs.url = "github:NixOS/nixpkgs/nixos-unstable";
    flake-utils.url = "github:numtide/flake-utils";
  };

  outputs = { self, nixpkgs, flake-utils }:
    flake-utils.lib.eachSystem [ "x86_64-linux" "aarch64-darwin" ] (system:
      let
        pkgs = import nixpkgs { inherit system; };

        php = pkgs.php82.buildEnv {
          extensions = { enabled, all }: enabled ++ (with all; [
            pcntl
            posix
            sockets
            mbstring
            intl
          ]);
          extraConfig = ''
            memory_limit = 256M
          '';
        };

        composer = pkgs.php82Packages.composer;

        # Wrapper script: run the async HTTP server
        run-server = pkgs.writeShellScriptBin "cohete-serve" ''
          set -euo pipefail
          if [ ! -d vendor ]; then
            echo "vendor/ not found. Run: composer install"
            exit 1
          fi
          exec ${php}/bin/php src/bootstrap.php "$@"
        '';

        # Wrapper script: run tests
        run-tests = pkgs.writeShellScriptBin "cohete-test" ''
          set -euo pipefail
          if [ ! -f vendor/bin/phpunit ]; then
            echo "phpunit not found. Run: composer install"
            exit 1
          fi
          exec vendor/bin/phpunit "$@"
        '';

      in {
        devShells.default = pkgs.mkShell {
          buildInputs = [
            php
            composer
            run-server
            run-tests
          ];

          shellHook = ''
            # Isolate from Vocento global composer config (Toran)
            # while preserving auth credentials for GitHub VCS repos
            export COMPOSER_HOME="$PWD/.composer-home"
            if [ ! -d "$COMPOSER_HOME" ]; then
              mkdir -p "$COMPOSER_HOME"
              # Copy auth.json if it exists (GitHub tokens, etc.)
              if [ -f "''${XDG_CONFIG_HOME:-$HOME/.config}/composer/auth.json" ]; then
                cp "''${XDG_CONFIG_HOME:-$HOME/.config}/composer/auth.json" "$COMPOSER_HOME/auth.json"
              fi
            fi

            echo "-------------------------------------------"
            echo " Cohete Skeleton - dev environment"
            echo "-------------------------------------------"
            echo "PHP:      $(php -r 'echo PHP_VERSION;')"
            echo "Composer: $(composer --version 2>/dev/null | head -1)"
            echo ""
            echo "Commands:"
            echo "  composer install    Install dependencies"
            echo "  cohete-serve        Start HTTP server on :8080"
            echo "  cohete-test         Run PHPUnit tests"
            echo "  make run            Start HTTP server (via Makefile)"
            echo "  make test           Run tests (via Makefile)"
            echo "-------------------------------------------"
          '';
        };

        apps.default = {
          type = "app";
          program = "${run-server}/bin/cohete-serve";
        };

        apps.test = {
          type = "app";
          program = "${run-tests}/bin/cohete-test";
        };
      }
    );
}
