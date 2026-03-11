{
  description = "Cohete skeleton - async PHP project";
  inputs = {
    nixpkgs.url = "github:NixOS/nixpkgs/nixos-unstable";
    flake-utils.url = "github:numtide/flake-utils";
  };
  outputs = { self, nixpkgs, flake-utils }:
    flake-utils.lib.eachDefaultSystem (system:
      let
        pkgs = nixpkgs.legacyPackages.${system};
        php = pkgs.php82.buildEnv {
          extensions = ({ enabled, all }: enabled ++ (with all; [ pcntl posix sockets mbstring intl ]));
          extraConfig = "memory_limit = 256M";
        };
      in {
        devShells.default = pkgs.mkShell {
          buildInputs = [ php pkgs.php82Packages.composer ];
          shellHook = ''
            echo "Cohete skeleton dev environment"
            echo "PHP: $(php --version | head -1)"
            echo "Run: php src/bootstrap.php"
            echo "Test: vendor/bin/phpunit"
          '';
        };
      }
    );
}
