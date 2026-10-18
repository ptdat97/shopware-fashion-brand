// Plugin Tailwind: class icon `i-carbon-<tên>` (bộ Carbon của Iconify).
//
// @shopware/cms-base-layer và template dùng cú pháp icon của UnoCSS (`i-carbon-cart`).
// Tailwind không có sẵn — plugin này sinh đúng tên class đó, cùng kiểu CSS UnoCSS đã
// dùng (mask + background-color: currentColor, 1em × 1em) để không phải sửa layer.
// Tailwind chỉ sinh CSS cho icon thực sự xuất hiện trong mã nguồn.
import { createRequire } from "node:module";

import { getIconData, iconToHTML, iconToSVG, svgToURL } from "@iconify/utils";
import plugin from "tailwindcss/plugin";

const require = createRequire(import.meta.url);
const carbon = require("@iconify-json/carbon/icons.json");

const iconNames = Object.fromEntries(
  [...Object.keys(carbon.icons), ...Object.keys(carbon.aliases ?? {})].map(
    (name) => [name, name],
  ),
);

function iconCss(name) {
  const data = getIconData(carbon, name);
  if (!data) return null;

  const { attributes, body } = iconToSVG(data, { height: "1em" });
  const url = svgToURL(iconToHTML(body, { ...attributes, width: "1em" }));

  return {
    "--tw-icon": url,
    "-webkit-mask": "var(--tw-icon) no-repeat",
    mask: "var(--tw-icon) no-repeat",
    "-webkit-mask-size": "100% 100%",
    "mask-size": "100% 100%",
    "background-color": "currentColor",
    color: "inherit",
    width: "1em",
    height: "1em",
  };
}

export default plugin(({ matchUtilities }) => {
  matchUtilities({ "i-carbon": iconCss }, { values: iconNames });
});
