declare module "#shopware" {
  import type { createAPIClient } from "@shopware/api-client";

  // Kiểu của Shopware + route Store API của plugin (api-types/storeApiTypes.overrides.ts)
  export type operations = import("@shopware/api-client").WithApiOverrides<
    import("@shopware/api-client/store-api-types").operations,
    import("./api-types/storeApiTypes.overrides").operations
  >;
  // or for locally generated types
  // export type operations = import("./api-types/storeApiTypes").operations;

  export type Schemas = import("@shopware/api-client").WithApiOverrides<
    import("@shopware/api-client/store-api-types").components["schemas"],
    import("./api-types/storeApiTypes.overrides").Schemas
  >;
  // or for locally generated types
  // export type Schemas =
  //   import("./api-types/storeApiTypes").components["schemas"];

  // we're exporting our own Api Client definition as it depends on our own instance
  export type ApiClient = ReturnType<typeof createAPIClient<operations>>;
}
