import { defineConfig } from "vite";
import typo3 from "vite-plugin-typo3";

export default defineConfig({
  plugins: [typo3({ aliases: "EXT" })],
  resolve: {
    // Manifest keys must be the vendor/ paths TYPO3 knows the extensions by
    // (vendor/webconsulting/<package>/...), whether vendor/ holds a symlink
    // (site_package's path repository, a clone linked by lab-link.sh) or a
    // real copy (the production image). vite-asset-collector 2 no longer falls
    // back to the symlink target, so an entry keyed packages/<clone>/... would
    // throw "Invalid asset file" (1690735353). vite-plugin-typo3 3 sets this
    // default already; it is spelled out so that no plugin change can drop it.
    preserveSymlinks: true,
  },
});
