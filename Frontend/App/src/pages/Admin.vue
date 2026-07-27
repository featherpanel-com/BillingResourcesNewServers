<script setup lang="ts">
import { ref, onMounted, computed } from "vue";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Loader2,
  Save,
  MapPin,
  Network,
  Box,
  Sparkles,
  Check,
} from "lucide-vue-next";
import {
  useSettingsAPI,
  type PluginSettings,
  type ResourceFieldPolicies,
  type PlacementFieldPolicies,
  type PlacementPolicyValue,
} from "@/composables/useSettingsAPI";
import { useToast } from "vue-toastification";
import axios from "axios";
import type { AxiosError } from "axios";

const toast = useToast();
const { loading, getSettings, updateSettings } = useSettingsAPI();

const settings = ref<PluginSettings | null>(null);
const saving = ref(false);

// Available options for selection
const allLocations = ref<Array<{ id: number; name: string }>>([]);
const allNodes = ref<Array<{ id: number; name: string; location_id: number }>>(
  []
);
const allRealms = ref<Array<{ id: number; name: string }>>([]);
const allSpells = ref<Array<{ id: number; name: string; realm_id: number }>>(
  []
);
const allUsers = ref<Array<{ id: number; username: string; email: string }>>(
  []
);

const loadingOptions = ref(false);

// Resource permission settings (per-resource)
const resourcePermissions = ref<{
  location: Record<number, { mode: "open" | "restricted"; error?: string }>;
  node: Record<number, { mode: "open" | "restricted"; error?: string }>;
  realm: Record<number, { mode: "open" | "restricted"; error?: string }>;
  spell: Record<number, { mode: "open" | "restricted"; error?: string }>;
}>({
  location: {},
  node: {},
  realm: {},
  spell: {},
});

// Realm filter for spells ('' = all, number = specific realm)
const selectedRealmFilter = ref<number | string | null>(null);

const RESOURCE_POLICY_KEYS = [
  "memory",
  "cpu",
  "disk",
  "swap",
  "io",
  "database_limit",
  "allocation_limit",
  "backup_limit",
] as const;

function emptyResourcePolicies(): ResourceFieldPolicies {
  const o: ResourceFieldPolicies = {};
  for (const k of RESOURCE_POLICY_KEYS) {
    o[k] = { mode: "user" };
  }
  return o;
}

function mergeResourcePolicies(
  incoming?: ResourceFieldPolicies | null
): ResourceFieldPolicies {
  const base = emptyResourcePolicies();
  if (!incoming) return base;
  for (const k of RESOURCE_POLICY_KEYS) {
    const row = incoming[k];
    if (!row) continue;
    const mode =
      row.mode === "fixed" || row.mode === "hidden" ? row.mode : "user";
    if (mode === "user") {
      base[k] = { mode: "user" };
      if (row.default != null && !Number.isNaN(Number(row.default))) {
        base[k].default = Number(row.default);
      }
    } else {
      base[k] = {
        mode,
        value: row.value != null ? Number(row.value) : 0,
      };
    }
  }
  return base;
}

const PLACEMENT_POLICY_KEYS = [
  "location",
  "node",
  "realm",
  "spell",
] as const;

function emptyPlacementPolicies(): PlacementFieldPolicies {
  const o: PlacementFieldPolicies = {};
  for (const k of PLACEMENT_POLICY_KEYS) {
    o[k] = { mode: "user" };
  }
  return o;
}

function mergePlacementPolicies(
  incoming?: PlacementFieldPolicies | null
): PlacementFieldPolicies {
  const base = emptyPlacementPolicies();
  if (!incoming) return base;
  for (const k of PLACEMENT_POLICY_KEYS) {
    const row = incoming[k];
    if (!row) continue;
    const mode =
      row.mode === "fixed" || row.mode === "hidden" ? row.mode : "user";
    const normalized = (v: unknown): PlacementPolicyValue | undefined => {
      if (v === null || v === undefined || v === "") return undefined;
      if (v === "first" || v === "least_capacity") return v;
      const n = Number(v);
      return Number.isNaN(n) ? undefined : n;
    };
    if (mode === "user") {
      base[k] = { mode: "user" };
      const d = normalized(row.default);
      if (d !== undefined) base[k].default = d;
    } else {
      base[k] = { mode };
      const val = normalized(row.value);
      if (val !== undefined) base[k].value = val;
    }
  }
  return base;
}

function placementSelectValue(
  key: (typeof PLACEMENT_POLICY_KEYS)[number],
  field: "value" | "default"
): string {
  const row = formSettings.value.placement_field_policies?.[key];
  if (!row) return "";
  const raw = field === "value" ? row.value : row.default;
  if (raw === null || raw === undefined) return "";
  return String(raw);
}

function setPlacementSelectValue(
  key: (typeof PLACEMENT_POLICY_KEYS)[number],
  field: "value" | "default",
  raw: string
) {
  const pol = formSettings.value.placement_field_policies;
  if (!pol?.[key]) return;
  const row = pol[key];
  if (raw === "") {
    if (field === "value") delete row.value;
    else delete row.default;
    return;
  }
  if (raw === "first" || raw === "least_capacity") {
    if (field === "value") row.value = raw;
    else row.default = raw;
    return;
  }
  const n = Number(raw);
  if (!Number.isNaN(n)) {
    if (field === "value") row.value = n;
    else row.default = n;
  }
}

const placementFieldRows = [
  {
    key: "location" as const,
    label: "Location",
    hint: "Datacenter / region for the server",
  },
  {
    key: "node" as const,
    label: "Node",
    hint: "Host machine; use “Least loaded” to auto-pick by capacity",
  },
  {
    key: "realm" as const,
    label: "Realm",
    hint: "Game / service category",
  },
  {
    key: "spell" as const,
    label: "Spell",
    hint: "Server type (egg); filtered by realm when set",
  },
];

const resourceFieldRows = [
  { key: "memory", label: "Memory (MB)", hint: "RAM for the new server" },
  { key: "cpu", label: "CPU (%)", hint: "CPU percentage cap" },
  { key: "disk", label: "Disk (MB)", hint: "Disk space" },
  { key: "swap", label: "Swap (MB)", hint: "Also enforced server-side if hidden" },
  { key: "io", label: "IO weight", hint: "Block IO weight (e.g. 500)" },
  {
    key: "database_limit",
    label: "Database limit",
    hint: "Max databases on this server",
  },
  {
    key: "allocation_limit",
    label: "Allocation limit",
    hint: "Max extra allocations (ports)",
  },
  { key: "backup_limit", label: "Backup limit", hint: "Max backups" },
];

// Form state
const formSettings = ref<PluginSettings>({
  user_creation_enabled: false,
  require_discord_link: false,
  user_restriction_mode: "all",
  allowed_users: [],
  allowed_locations: [],
  allowed_nodes: [],
  allowed_realms: [],
  allowed_spells: [],
  minimum_memory: 128,
  minimum_cpu: 0,
  minimum_disk: 128,
  max_servers_per_node: 0,
  node_server_caps: {},
  node_at_capacity_error:
    "This node has reached the maximum of {max} servers",
  permission_mode_location: "open",
  permission_mode_node: "open",
  permission_mode_realm: "open",
  permission_mode_spell: "open",
  default_error_location: "You do not have permission to use this location",
  default_error_node: "You do not have permission to use this node",
  default_error_realm: "You do not have permission to use this realm",
  default_error_spell: "You do not have permission to use this spell",
  resource_field_policies: emptyResourcePolicies(),
  placement_field_policies: emptyPlacementPolicies(),
});

const nodesByLocation = computed(() => {
  const groups: Array<{
    locationId: number;
    locationName: string;
    nodes: Array<{ id: number; name: string; location_id: number }>;
  }> = [];
  const byLoc = new Map<
    number,
    Array<{ id: number; name: string; location_id: number }>
  >();
  for (const node of allNodes.value) {
    const lid = node.location_id ?? 0;
    if (!byLoc.has(lid)) {
      byLoc.set(lid, []);
    }
    byLoc.get(lid)!.push(node);
  }
  for (const [locationId, nodes] of byLoc) {
    const loc = allLocations.value.find((l) => l.id === locationId);
    groups.push({
      locationId,
      locationName: loc?.name ?? `Location #${locationId}`,
      nodes: [...nodes].sort((a, b) => a.name.localeCompare(b.name)),
    });
  }
  return groups.sort((a, b) => a.locationName.localeCompare(b.locationName));
});

function getNodeServerCapInput(nodeId: number): string {
  const caps = formSettings.value.node_server_caps ?? {};
  const v = caps[nodeId];
  return v != null && v > 0 ? String(v) : "";
}

function setNodeServerCapInput(nodeId: number, raw: string) {
  if (!formSettings.value.node_server_caps) {
    formSettings.value.node_server_caps = {};
  }
  const caps = { ...formSettings.value.node_server_caps };
  const n = Number(raw);
  if (raw.trim() === "" || Number.isNaN(n) || n <= 0) {
    delete caps[nodeId];
  } else {
    caps[nodeId] = Math.floor(n);
  }
  formSettings.value.node_server_caps = caps;
}

function effectiveNodeCap(nodeId: number): number {
  const caps = formSettings.value.node_server_caps ?? {};
  if (caps[nodeId] != null && caps[nodeId] > 0) {
    return caps[nodeId];
  }
  return formSettings.value.max_servers_per_node ?? 0;
}

// Filtered spells based on selected realm
const filteredSpells = computed(() => {
  const filter = selectedRealmFilter.value;
  if (filter === null || filter === "" || filter === undefined) {
    return allSpells.value;
  }
  const filterId = typeof filter === "string" ? parseInt(filter, 10) : filter;
  return allSpells.value.filter(
    (s: { id: number; name: string; realm_id: number }) =>
      s.realm_id === filterId
  );
});

const loadSettings = async () => {
  try {
    settings.value = await getSettings();
    if (settings.value) {
      formSettings.value = {
        ...settings.value,
        resource_field_policies: mergeResourcePolicies(
          settings.value.resource_field_policies
        ),
        placement_field_policies: mergePlacementPolicies(
          settings.value.placement_field_policies
        ),
      };
    }
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to load settings");
  }
};

const loadResourcePermissions = async () => {
  try {
    const [locationsPerms, nodesPerms, realmsPerms, spellsPerms] =
      await Promise.all([
        axios
          .get(
            "/api/admin/billingresourcesnewservers/resource-permissions/location"
          )
          .catch(() => ({ data: { data: [] } })),
        axios
          .get(
            "/api/admin/billingresourcesnewservers/resource-permissions/node"
          )
          .catch(() => ({ data: { data: [] } })),
        axios
          .get(
            "/api/admin/billingresourcesnewservers/resource-permissions/realm"
          )
          .catch(() => ({ data: { data: [] } })),
        axios
          .get(
            "/api/admin/billingresourcesnewservers/resource-permissions/spell"
          )
          .catch(() => ({ data: { data: [] } })),
      ]);

    // Initialize permission maps
    resourcePermissions.value.location = {};
    resourcePermissions.value.node = {};
    resourcePermissions.value.realm = {};
    resourcePermissions.value.spell = {};

    // Process location permissions
    (locationsPerms.data?.data || []).forEach((perm: any) => {
      resourcePermissions.value.location[perm.resource_id] = {
        mode: perm.permission_mode || "open",
        error: perm.default_error_message || undefined,
      };
    });

    // Process node permissions
    (nodesPerms.data?.data || []).forEach((perm: any) => {
      resourcePermissions.value.node[perm.resource_id] = {
        mode: perm.permission_mode || "open",
        error: perm.default_error_message || undefined,
      };
    });

    // Process realm permissions
    (realmsPerms.data?.data || []).forEach((perm: any) => {
      resourcePermissions.value.realm[perm.resource_id] = {
        mode: perm.permission_mode || "open",
        error: perm.default_error_message || undefined,
      };
    });

    // Process spell permissions
    (spellsPerms.data?.data || []).forEach((perm: any) => {
      resourcePermissions.value.spell[perm.resource_id] = {
        mode: perm.permission_mode || "open",
        error: perm.default_error_message || undefined,
      };
    });
  } catch (err) {
    console.error("Failed to load resource permissions:", err);
    // Don't show error toast, just use defaults
  }
};

const loadOptions = async () => {
  loadingOptions.value = true;
  try {
    const [locationsRes, nodesRes, realmsRes, spellsRes, usersRes] =
      await Promise.all([
        axios.get("/api/admin/locations", { params: { limit: 1000 } }),
        axios.get("/api/admin/nodes", { params: { limit: 1000 } }),
        axios.get("/api/admin/realms", { params: { limit: 1000 } }),
        axios.get("/api/admin/spells", { params: { limit: 1000 } }),
        axios.get("/api/admin/users", { params: { limit: 1000 } }),
      ]);

    allLocations.value = locationsRes.data?.data?.locations || [];
    allNodes.value = nodesRes.data?.data?.nodes || [];
    allRealms.value = realmsRes.data?.data?.realms || [];
    allSpells.value = spellsRes.data?.data?.spells || [];
    allUsers.value = usersRes.data?.data?.users || [];

    // Load resource permissions after resources are loaded
    await loadResourcePermissions();
  } catch (err) {
    const errorMsg =
      err instanceof Error ? err.message : "Failed to load options";
    const axiosError = err as AxiosError<{ message?: string }>;
    toast.error(axiosError?.response?.data?.message || errorMsg);
  } finally {
    loadingOptions.value = false;
  }
};

const getResourcePermissionMode = (
  resourceType: "location" | "node" | "realm" | "spell",
  resourceId: number
): "open" | "restricted" => {
  return resourcePermissions.value[resourceType]?.[resourceId]?.mode || "open";
};

const setResourcePermissionMode = async (
  resourceType: "location" | "node" | "realm" | "spell",
  resourceId: number,
  mode: "open" | "restricted",
  errorMessage?: string
) => {
  try {
    await axios.post(
      "/api/admin/billingresourcesnewservers/resource-permissions",
      {
        resource_type: resourceType,
        resource_id: resourceId,
        permission_mode: mode,
        default_error_message: errorMessage || null,
      }
    );

    // Update local state
    if (!resourcePermissions.value[resourceType]) {
      resourcePermissions.value[resourceType] = {};
    }
    resourcePermissions.value[resourceType][resourceId] = {
      mode,
      error: errorMessage,
    };

    toast.success(`${resourceType} permission updated`);
  } catch (err) {
    const axiosError = err as AxiosError<{ error_message?: string }>;
    toast.error(
      axiosError?.response?.data?.error_message || "Failed to update permission"
    );
  }
};

const toggleLocation = (locationId: number) => {
  const index = formSettings.value.allowed_locations.indexOf(locationId);
  if (index > -1) {
    formSettings.value.allowed_locations.splice(index, 1);
  } else {
    formSettings.value.allowed_locations.push(locationId);
  }
};

const toggleNode = (nodeId: number) => {
  const index = formSettings.value.allowed_nodes.indexOf(nodeId);
  if (index > -1) {
    formSettings.value.allowed_nodes.splice(index, 1);
  } else {
    formSettings.value.allowed_nodes.push(nodeId);
  }
};

const toggleRealm = (realmId: number) => {
  const index = formSettings.value.allowed_realms.indexOf(realmId);
  if (index > -1) {
    formSettings.value.allowed_realms.splice(index, 1);
  } else {
    formSettings.value.allowed_realms.push(realmId);
  }
};

const toggleSpell = (spellId: number) => {
  const index = formSettings.value.allowed_spells.indexOf(spellId);
  if (index > -1) {
    formSettings.value.allowed_spells.splice(index, 1);
  } else {
    formSettings.value.allowed_spells.push(spellId);
  }
};

const isLocationSelected = (locationId: number) => {
  return formSettings.value.allowed_locations.includes(locationId);
};

const isNodeSelected = (nodeId: number) => {
  return formSettings.value.allowed_nodes.includes(nodeId);
};

const isRealmSelected = (realmId: number) => {
  return formSettings.value.allowed_realms.includes(realmId);
};

const isSpellSelected = (spellId: number) => {
  return formSettings.value.allowed_spells.includes(spellId);
};

const selectAllLocations = () => {
  formSettings.value.allowed_locations = allLocations.value.map((l) => l.id);
};

const clearAllLocations = () => {
  formSettings.value.allowed_locations = [];
};

const selectAllNodes = () => {
  formSettings.value.allowed_nodes = allNodes.value.map((n) => n.id);
};

const clearAllNodes = () => {
  formSettings.value.allowed_nodes = [];
};

const selectAllRealms = () => {
  formSettings.value.allowed_realms = allRealms.value.map((r) => r.id);
};

const clearAllRealms = () => {
  formSettings.value.allowed_realms = [];
};

const selectAllSpells = () => {
  formSettings.value.allowed_spells = filteredSpells.value.map((s) => s.id);
};

const clearAllSpells = () => {
  formSettings.value.allowed_spells = [];
};

const saveSettings = async () => {
  saving.value = true;
  try {
    const updated = await updateSettings(formSettings.value);
    settings.value = updated;
    formSettings.value = {
      ...updated,
      resource_field_policies: mergeResourcePolicies(
        updated.resource_field_policies
      ),
    };
    toast.success("Settings saved successfully!");
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to save settings");
  } finally {
    saving.value = false;
  }
};

onMounted(async () => {
  await Promise.all([loadSettings(), loadOptions()]);
});
</script>

<template>
  <div class="w-full h-full overflow-auto p-4 md:p-8 min-h-screen">
    <div class="container mx-auto max-w-7xl">
      <div class="mb-6 text-center md:text-left">
        <h1
          class="text-3xl font-bold bg-gradient-to-r from-primary to-primary/60 bg-clip-text text-transparent"
        >
          New Server Settings
        </h1>
        <p class="text-muted-foreground mt-2">
          Configure user server creation options and restrictions
        </p>
      </div>

      <Card v-if="loading && !settings" class="p-6 border-2 shadow-xl bg-card/50 backdrop-blur-sm">
        <div class="flex items-center justify-center py-12">
          <Loader2 class="h-8 w-8 animate-spin" />
        </div>
      </Card>

      <form
        v-else-if="settings"
        @submit.prevent="saveSettings"
        class="space-y-6"
      >
        <!-- Enable/Disable User Creation -->
        <Card class="p-6 border-2 shadow-xl bg-card/50 backdrop-blur-sm">
          <div class="space-y-4">
            <div class="flex items-center justify-between p-4 rounded-lg bg-muted/30 border border-border/50">
              <div class="space-y-1">
                <Label class="text-base font-semibold"
                  >Enable User Server Creation</Label
                >
                <p class="text-sm text-muted-foreground">
                  Allow users to create new servers using their available
                  resources
                </p>
              </div>
              <button
                type="button"
                role="switch"
                :aria-checked="formSettings.user_creation_enabled"
                @click="formSettings.user_creation_enabled = !formSettings.user_creation_enabled"
                :class="[
                  'relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-background',
                  formSettings.user_creation_enabled ? 'bg-primary' : 'bg-muted',
                ]"
              >
                <span
                  class="pointer-events-none block h-5 w-5 rounded-full bg-white shadow-lg ring-0 transition-transform"
                  :class="
                    formSettings.user_creation_enabled ? 'translate-x-5' : 'translate-x-0.5'
                  "
                />
              </button>
            </div>
            <div class="flex items-center justify-between p-4 rounded-lg bg-muted/30 border border-border/50">
              <div class="space-y-1">
                <Label class="text-base font-semibold"
                  >Require Discord Account Linking</Label
                >
                <p class="text-sm text-muted-foreground">
                  Users must link Discord before creating a free-tier
                  (freemium) server. Requires Discord OAuth to be enabled in
                  panel settings.
                </p>
              </div>
              <button
                type="button"
                role="switch"
                :aria-checked="!!formSettings.require_discord_link"
                @click="
                  formSettings.require_discord_link =
                    !formSettings.require_discord_link
                "
                :class="[
                  'relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-background',
                  formSettings.require_discord_link ? 'bg-primary' : 'bg-muted',
                ]"
              >
                <span
                  class="pointer-events-none block h-5 w-5 rounded-full bg-white shadow-lg ring-0 transition-transform"
                  :class="
                    formSettings.require_discord_link
                      ? 'translate-x-5'
                      : 'translate-x-0.5'
                  "
                />
              </button>
            </div>
          </div>
        </Card>

        <!-- User Restrictions -->
        <Card class="p-6 border-2 shadow-xl bg-card/50 backdrop-blur-sm">
          <div class="mb-4">
            <Label class="text-base font-semibold">User Access Control</Label>
            <p class="text-sm text-muted-foreground mt-1">
              Control whether server creation requires permissions or is open to
              everyone
            </p>
          </div>
          <div class="space-y-4">
            <div>
              <Label for="user_restriction_mode">Access Mode</Label>
              <div class="flex items-center space-x-4 mt-2">
                <label class="flex items-center">
                  <input
                    type="radio"
                    value="all"
                    v-model="formSettings.user_restriction_mode"
                    class="h-4 w-4 text-primary border-gray-300 focus:ring-primary"
                  />
                  <span class="ml-2 text-sm">Open to Everyone</span>
                </label>
                <label class="flex items-center">
                  <input
                    type="radio"
                    value="specific"
                    v-model="formSettings.user_restriction_mode"
                    class="h-4 w-4 text-primary border-gray-300 focus:ring-primary"
                  />
                  <span class="ml-2 text-sm">Permission Required</span>
                </label>
              </div>
              <p class="text-xs text-muted-foreground mt-1">
                {{
                  formSettings.user_restriction_mode === "all"
                    ? "All authenticated users can create servers (subject to resource-level permissions)"
                    : "Only users with permissions (individual or group) can create servers"
                }}
              </p>
            </div>

            <div
              v-if="formSettings.user_restriction_mode === 'specific'"
              class="mt-4"
            >
              <p class="text-sm text-muted-foreground mb-2">
                When "Permission Required" is enabled, users must have
                individual permissions or be in a group with permissions to
                create servers. Use the "User Permissions" page to assign
                permissions to users or groups.
              </p>
            </div>
          </div>
        </Card>

        <!-- Minimum Resource Requirements -->
        <Card class="p-6 border-2 shadow-xl bg-card/50 backdrop-blur-sm">
          <div class="mb-4">
            <Label class="text-base font-semibold"
              >Minimum Resource Requirements</Label
            >
            <p class="text-sm text-muted-foreground mt-1">
              Set the minimum resources required for users to create servers
            </p>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <Label for="minimum_memory">Minimum Memory (MB)</Label>
              <Input
                id="minimum_memory"
                v-model.number="formSettings.minimum_memory"
                type="number"
                min="128"
                class="mt-2"
              />
              <p class="text-xs text-muted-foreground mt-1">
                Minimum memory required (default: 128 MB)
              </p>
            </div>
            <div>
              <Label for="minimum_cpu">Minimum CPU (%)</Label>
              <Input
                id="minimum_cpu"
                v-model.number="formSettings.minimum_cpu"
                type="number"
                min="0"
                class="mt-2"
              />
              <p class="text-xs text-muted-foreground mt-1">
                Minimum CPU required (default: 0%)
              </p>
            </div>
            <div>
              <Label for="minimum_disk">Minimum Disk (MB)</Label>
              <Input
                id="minimum_disk"
                v-model.number="formSettings.minimum_disk"
                type="number"
                min="128"
                class="mt-2"
              />
              <p class="text-xs text-muted-foreground mt-1">
                Minimum disk required (default: 128 MB)
              </p>
            </div>
          </div>
        </Card>

        <!-- Per-field defaults / lock (create server form) -->
        <Card class="p-6 border-2 shadow-xl bg-card/50 backdrop-blur-sm">
          <div class="mb-4">
            <Label class="text-base font-semibold">
              Create server form — defaults & locked resources
            </Label>
            <p class="text-sm text-muted-foreground mt-1">
              <span class="font-medium">User choice</span>: players can edit
              the value (optional default pre-fills the field).
              <span class="font-medium">Fixed</span>: always use the value below;
              shown read-only on the create page.
              <span class="font-medium">Hidden</span>: not shown; the value is
              always applied server-side (users cannot change it).
            </p>
          </div>
          <div class="space-y-4">
            <div
              v-for="row in resourceFieldRows"
              :key="row.key"
              class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end border-b border-border/50 pb-4 last:border-0 last:pb-0"
            >
              <div class="md:col-span-3">
                <p class="font-medium">{{ row.label }}</p>
                <p class="text-xs text-muted-foreground">{{ row.hint }}</p>
              </div>
              <div class="md:col-span-3">
                <Label class="text-xs">Mode</Label>
                <select
                  v-model="formSettings.resource_field_policies![row.key].mode"
                  class="mt-1 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                  <option value="user">User choice</option>
                  <option value="fixed">Fixed (visible)</option>
                  <option value="hidden">Hidden (forced)</option>
                </select>
              </div>
              <div
                v-if="formSettings.resource_field_policies![row.key].mode !== 'user'"
                class="md:col-span-3"
              >
                <Label class="text-xs">Forced value</Label>
                <Input
                  v-model.number="formSettings.resource_field_policies![row.key].value"
                  type="number"
                  min="0"
                  class="mt-1"
                />
              </div>
              <div
                v-if="formSettings.resource_field_policies![row.key].mode === 'user'"
                class="md:col-span-3"
              >
                <Label class="text-xs">Default (optional)</Label>
                <Input
                  v-model.number="formSettings.resource_field_policies![row.key].default"
                  type="number"
                  min="0"
                  class="mt-1"
                  placeholder="Panel default"
                />
              </div>
            </div>
          </div>
        </Card>

        <!-- Placement defaults / lock (location, node, realm, spell) -->
        <Card class="p-6 border-2 shadow-xl bg-card/50 backdrop-blur-sm">
          <div class="mb-4">
            <Label class="text-base font-semibold">
              Create server form — location, node, realm & spell
            </Label>
            <p class="text-sm text-muted-foreground mt-1">
              Same modes as resource fields. Use
              <span class="font-medium">First available</span> to pre-select or
              force the first allowed option.
              <span class="font-medium">Least loaded node</span> spreads new
              servers across nodes (respects max servers per node, memory/disk,
              and free allocations).
            </p>
          </div>
          <div class="space-y-4">
            <div
              v-for="row in placementFieldRows"
              :key="row.key"
              class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end border-b border-border/50 pb-4 last:border-0 last:pb-0"
            >
              <div class="md:col-span-3">
                <p class="font-medium">{{ row.label }}</p>
                <p class="text-xs text-muted-foreground">{{ row.hint }}</p>
              </div>
              <div class="md:col-span-3">
                <Label class="text-xs">Mode</Label>
                <select
                  v-model="formSettings.placement_field_policies![row.key].mode"
                  class="mt-1 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                  <option value="user">User choice</option>
                  <option value="fixed">Fixed (visible)</option>
                  <option value="hidden">Hidden (forced)</option>
                </select>
              </div>
              <div
                v-if="
                  formSettings.placement_field_policies![row.key].mode !==
                  'user'
                "
                class="md:col-span-3"
              >
                <Label class="text-xs">Forced selection</Label>
                <select
                  :value="placementSelectValue(row.key, 'value')"
                  class="mt-1 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                  @change="
                    setPlacementSelectValue(
                      row.key,
                      'value',
                      ($event.target as HTMLSelectElement).value
                    )
                  "
                >
                  <option value="">— Select —</option>
                  <option value="first">First available</option>
                  <option
                    v-if="row.key === 'node'"
                    value="least_capacity"
                  >
                    Least loaded node
                  </option>
                  <template v-if="row.key === 'location'">
                    <option
                      v-for="loc in allLocations"
                      :key="loc.id"
                      :value="String(loc.id)"
                    >
                      {{ loc.name }} (#{{ loc.id }})
                    </option>
                  </template>
                  <template v-else-if="row.key === 'node'">
                    <option
                      v-for="node in allNodes"
                      :key="node.id"
                      :value="String(node.id)"
                    >
                      {{ node.name }} (#{{ node.id }})
                    </option>
                  </template>
                  <template v-else-if="row.key === 'realm'">
                    <option
                      v-for="realm in allRealms"
                      :key="realm.id"
                      :value="String(realm.id)"
                    >
                      {{ realm.name }} (#{{ realm.id }})
                    </option>
                  </template>
                  <template v-else-if="row.key === 'spell'">
                    <option
                      v-for="spell in allSpells"
                      :key="spell.id"
                      :value="String(spell.id)"
                    >
                      {{ spell.name }} (#{{ spell.id }})
                    </option>
                  </template>
                </select>
              </div>
              <div
                v-if="
                  formSettings.placement_field_policies![row.key].mode ===
                  'user'
                "
                class="md:col-span-3"
              >
                <Label class="text-xs">Default (optional)</Label>
                <select
                  :value="placementSelectValue(row.key, 'default')"
                  class="mt-1 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                  @change="
                    setPlacementSelectValue(
                      row.key,
                      'default',
                      ($event.target as HTMLSelectElement).value
                    )
                  "
                >
                  <option value="">None (user picks)</option>
                  <option value="first">First available</option>
                  <option
                    v-if="row.key === 'node'"
                    value="least_capacity"
                  >
                    Least loaded node
                  </option>
                  <template v-if="row.key === 'location'">
                    <option
                      v-for="loc in allLocations"
                      :key="'d-loc-' + loc.id"
                      :value="String(loc.id)"
                    >
                      {{ loc.name }} (#{{ loc.id }})
                    </option>
                  </template>
                  <template v-else-if="row.key === 'node'">
                    <option
                      v-for="node in allNodes"
                      :key="'d-node-' + node.id"
                      :value="String(node.id)"
                    >
                      {{ node.name }} (#{{ node.id }})
                    </option>
                  </template>
                  <template v-else-if="row.key === 'realm'">
                    <option
                      v-for="realm in allRealms"
                      :key="'d-realm-' + realm.id"
                      :value="String(realm.id)"
                    >
                      {{ realm.name }} (#{{ realm.id }})
                    </option>
                  </template>
                  <template v-else-if="row.key === 'spell'">
                    <option
                      v-for="spell in allSpells"
                      :key="'d-spell-' + spell.id"
                      :value="String(spell.id)"
                    >
                      {{ spell.name }} (#{{ spell.id }})
                    </option>
                  </template>
                </select>
              </div>
            </div>
          </div>
        </Card>

        <!-- Allowed Locations -->
        <Card class="p-6 border-2 shadow-xl bg-card/50 backdrop-blur-sm">
          <div class="mb-4">
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center gap-2">
                <MapPin class="h-5 w-5" />
                <Label class="text-base font-semibold">Allowed Locations</Label>
              </div>
              <div class="flex gap-2">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  @click="selectAllLocations"
                >
                  Select All
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  @click="clearAllLocations"
                >
                  Clear All
                </Button>
              </div>
            </div>
            <p class="text-sm text-muted-foreground">
              Select which locations users can use. Leave empty to allow all
              locations.
            </p>
          </div>
          <div
            v-if="loadingOptions"
            class="flex items-center justify-center py-8"
          >
            <Loader2 class="h-6 w-6 animate-spin" />
          </div>
          <div
            v-else
            class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2"
          >
            <div
              v-for="location in allLocations"
              :key="location.id"
              class="p-3 border rounded-lg"
              :class="{
                'border-primary bg-primary/10': isLocationSelected(location.id),
              }"
            >
              <div class="flex items-center justify-between mb-2">
                <div
                  class="flex items-center gap-2 flex-1 cursor-pointer"
                  @click="toggleLocation(location.id)"
                >
                  <div
                    class="flex h-5 w-5 items-center justify-center rounded border"
                    :class="{
                      'bg-primary border-primary': isLocationSelected(
                        location.id
                      ),
                    }"
                  >
                    <Check
                      v-if="isLocationSelected(location.id)"
                      class="h-4 w-4 text-primary-foreground"
                    />
                  </div>
                  <span class="font-medium">{{ location.name }}</span>
                </div>
                <div class="flex items-center gap-2">
                  <select
                    :value="getResourcePermissionMode('location', location.id)"
                    @change="
                      setResourcePermissionMode(
                        'location',
                        location.id,
                        ($event.target as HTMLSelectElement).value as
                          | 'open'
                          | 'restricted'
                      )
                    "
                    class="flex h-8 rounded-md border border-input bg-background px-2 py-1 text-xs text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                    @click.stop
                  >
                    <option value="open">Open</option>
                    <option value="restricted">Restricted</option>
                  </select>
                </div>
              </div>
              <div
                v-if="
                  getResourcePermissionMode('location', location.id) ===
                  'restricted'
                "
                class="mt-2"
              >
                <Input
                  :model-value="
                    resourcePermissions.location?.[location.id]?.error || ''
                  "
                  @update:model-value="
                    setResourcePermissionMode(
                      'location',
                      location.id,
                      'restricted',
                      String($event ?? '')
                    )
                  "
                  placeholder="Default error message"
                  class="w-full text-xs"
                  @click.stop
                />
              </div>
            </div>
          </div>
        </Card>

        <!-- Allowed Nodes -->
        <Card class="p-6 border-2 shadow-xl bg-card/50 backdrop-blur-sm">
          <div class="mb-4">
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center gap-2">
                <Network class="h-5 w-5" />
                <Label class="text-base font-semibold">Allowed Nodes</Label>
              </div>
              <div class="flex gap-2">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  @click="selectAllNodes"
                >
                  Select All
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  @click="clearAllNodes"
                >
                  Clear All
                </Button>
              </div>
            </div>
            <p class="text-sm text-muted-foreground">
              Nodes are grouped by location. Set a <strong>max servers</strong>
              on each node (not on the location). Leave empty to allow all nodes.
            </p>
          </div>
          <div
            class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 p-4 border rounded-lg bg-muted/20"
          >
            <div>
              <Label for="max_servers_per_node">Default max servers (per node)</Label>
              <Input
                id="max_servers_per_node"
                v-model.number="formSettings.max_servers_per_node"
                type="number"
                min="0"
                class="mt-2"
              />
              <p class="text-xs text-muted-foreground mt-1">
                Fallback when a node has no individual cap (0 = unlimited).
              </p>
            </div>
            <div>
              <Label for="node_at_capacity_error">Node full — error message</Label>
              <Input
                id="node_at_capacity_error"
                v-model="formSettings.node_at_capacity_error"
                placeholder="This node has reached the maximum of {max} servers"
                class="mt-2"
              />
              <p class="text-xs text-muted-foreground mt-1">
                Use <code class="text-xs">{max}</code> for that node’s limit.
              </p>
            </div>
          </div>
          <div
            v-if="loadingOptions"
            class="flex items-center justify-center py-8"
          >
            <Loader2 class="h-6 w-6 animate-spin" />
          </div>
          <div v-else class="space-y-6">
            <div
              v-for="group in nodesByLocation"
              :key="group.locationId"
              class="space-y-2"
            >
              <h3
                class="text-sm font-semibold text-muted-foreground flex items-center gap-2"
              >
                <MapPin class="h-4 w-4" />
                {{ group.locationName }}
              </h3>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                <div
                  v-for="node in group.nodes"
                  :key="node.id"
                  class="p-3 border rounded-lg"
                  :class="{
                    'border-primary bg-primary/10': isNodeSelected(node.id),
                  }"
                >
                  <div class="flex items-center justify-between mb-2">
                    <div
                      class="flex items-center gap-2 flex-1 cursor-pointer"
                      @click="toggleNode(node.id)"
                    >
                      <div
                        class="flex h-5 w-5 items-center justify-center rounded border shrink-0"
                        :class="{
                          'bg-primary border-primary': isNodeSelected(node.id),
                        }"
                      >
                        <Check
                          v-if="isNodeSelected(node.id)"
                          class="h-4 w-4 text-primary-foreground"
                        />
                      </div>
                      <span class="font-medium">{{ node.name }}</span>
                    </div>
                    <select
                      :value="getResourcePermissionMode('node', node.id)"
                      @change="
                        setResourcePermissionMode(
                          'node',
                          node.id,
                          ($event.target as HTMLSelectElement).value as
                            | 'open'
                            | 'restricted'
                        )
                      "
                      class="flex h-8 rounded-md border border-input bg-background px-2 py-1 text-xs text-foreground focus:outline-none focus:ring-2 focus:ring-ring shrink-0"
                      @click.stop
                    >
                      <option value="open">Open</option>
                      <option value="restricted">Restricted</option>
                    </select>
                  </div>
                  <div class="mt-2" @click.stop>
                    <Label class="text-xs">Max servers on this node</Label>
                    <Input
                      :model-value="getNodeServerCapInput(node.id)"
                      type="number"
                      min="0"
                      placeholder="Default"
                      class="mt-1 h-8 text-sm"
                      @update:model-value="
                        setNodeServerCapInput(node.id, String($event ?? ''))
                      "
                    />
                    <p class="text-xs text-muted-foreground mt-1">
                      <template v-if="getNodeServerCapInput(node.id)">
                        Cap: {{ getNodeServerCapInput(node.id) }} servers
                      </template>
                      <template v-else-if="effectiveNodeCap(node.id) > 0">
                        Cap: {{ effectiveNodeCap(node.id) }} (default)
                      </template>
                      <template v-else>Unlimited</template>
                    </p>
                  </div>
                  <div
                    v-if="
                      getResourcePermissionMode('node', node.id) ===
                      'restricted'
                    "
                    class="mt-2"
                  >
                    <Input
                      :model-value="
                        resourcePermissions.node?.[node.id]?.error || ''
                      "
                      @update:model-value="
                        setResourcePermissionMode(
                          'node',
                          node.id,
                          'restricted',
                          String($event ?? '')
                        )
                      "
                      placeholder="Default error message"
                      class="w-full text-xs"
                      @click.stop
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </Card>

        <!-- Allowed Realms -->
        <Card class="p-6 border-2 shadow-xl bg-card/50 backdrop-blur-sm">
          <div class="mb-4">
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center gap-2">
                <Box class="h-5 w-5" />
                <Label class="text-base font-semibold">Allowed Realms</Label>
              </div>
              <div class="flex gap-2">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  @click="selectAllRealms"
                >
                  Select All
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  @click="clearAllRealms"
                >
                  Clear All
                </Button>
              </div>
            </div>
            <p class="text-sm text-muted-foreground">
              Select which realms users can use. Leave empty to allow all
              realms.
            </p>
          </div>
          <div
            v-if="loadingOptions"
            class="flex items-center justify-center py-8"
          >
            <Loader2 class="h-6 w-6 animate-spin" />
          </div>
          <div
            v-else
            class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2"
          >
            <div
              v-for="realm in allRealms"
              :key="realm.id"
              class="p-3 border rounded-lg"
              :class="{
                'border-primary bg-primary/10': isRealmSelected(realm.id),
              }"
            >
              <div class="flex items-center justify-between mb-2">
                <div
                  class="flex items-center gap-2 flex-1 cursor-pointer"
                  @click="toggleRealm(realm.id)"
                >
                  <div
                    class="flex h-5 w-5 items-center justify-center rounded border"
                    :class="{
                      'bg-primary border-primary': isRealmSelected(realm.id),
                    }"
                  >
                    <Check
                      v-if="isRealmSelected(realm.id)"
                      class="h-4 w-4 text-primary-foreground"
                    />
                  </div>
                  <span class="font-medium">{{ realm.name }}</span>
                </div>
                <div class="flex items-center gap-2">
                  <select
                    :value="getResourcePermissionMode('realm', realm.id)"
                    @change="
                      setResourcePermissionMode(
                        'realm',
                        realm.id,
                        ($event.target as HTMLSelectElement).value as
                          | 'open'
                          | 'restricted'
                      )
                    "
                    class="flex h-8 rounded-md border border-input bg-background px-2 py-1 text-xs text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                    @click.stop
                  >
                    <option value="open">Open</option>
                    <option value="restricted">Restricted</option>
                  </select>
                </div>
              </div>
              <div
                v-if="
                  getResourcePermissionMode('realm', realm.id) === 'restricted'
                "
                class="mt-2"
              >
                <Input
                  :model-value="resourcePermissions.realm?.[realm.id]?.error || ''"
                  @update:model-value="
                    setResourcePermissionMode(
                      'realm',
                      realm.id,
                      'restricted',
                      String($event ?? '')
                    )
                  "
                  placeholder="Default error message"
                  class="w-full text-xs"
                  @click.stop
                />
              </div>
            </div>
          </div>
        </Card>

        <!-- Allowed Spells -->
        <Card class="p-6 border-2 shadow-xl bg-card/50 backdrop-blur-sm">
          <div class="mb-4">
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center gap-2">
                <Sparkles class="h-5 w-5" />
                <Label class="text-base font-semibold">Allowed Spells</Label>
              </div>
              <div class="flex gap-2">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  @click="selectAllSpells"
                >
                  Select All
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  @click="clearAllSpells"
                >
                  Clear All
                </Button>
              </div>
            </div>
            <p class="text-sm text-muted-foreground mb-3">
              Select which spells (nests) users can use. Leave empty to allow
              all spells.
            </p>
            <!-- Realm Filter for Spells -->
            <div class="mb-3">
              <Label for="realm-filter" class="text-sm font-medium mb-2 block">
                Filter by Realm
              </Label>
              <select
                id="realm-filter"
                v-model="selectedRealmFilter"
                class="flex h-9 w-full md:w-64 rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
              >
                <option value="">All Realms</option>
                <option
                  v-for="realm in allRealms"
                  :key="realm.id"
                  :value="realm.id"
                >
                  {{ realm.name }}
                </option>
              </select>
            </div>
          </div>
          <div
            v-if="loadingOptions"
            class="flex items-center justify-center py-8"
          >
            <Loader2 class="h-6 w-6 animate-spin" />
          </div>
          <div
            v-else-if="filteredSpells.length === 0"
            class="text-center py-8 text-muted-foreground"
          >
            <p>No spells found for the selected realm.</p>
          </div>
          <div
            v-else
            class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2"
          >
            <div
              v-for="spell in filteredSpells"
              :key="spell.id"
              class="p-3 border rounded-lg"
              :class="{
                'border-primary bg-primary/10': isSpellSelected(spell.id),
              }"
            >
              <div class="flex items-center justify-between mb-2">
                <div
                  class="flex items-center gap-2 flex-1 cursor-pointer"
                  @click="toggleSpell(spell.id)"
                >
                  <div
                    class="flex h-5 w-5 items-center justify-center rounded border"
                    :class="{
                      'bg-primary border-primary': isSpellSelected(spell.id),
                    }"
                  >
                    <Check
                      v-if="isSpellSelected(spell.id)"
                      class="h-4 w-4 text-primary-foreground"
                    />
                  </div>
                  <div class="flex-1">
                    <span class="font-medium">{{ spell.name }}</span>
                    <p class="text-xs text-muted-foreground">
                      Realm:
                      {{
                        allRealms.find((r) => r.id === spell.realm_id)?.name ||
                        "Unknown"
                      }}
                    </p>
                  </div>
                </div>
                <div class="flex items-center gap-2">
                  <select
                    :value="getResourcePermissionMode('spell', spell.id)"
                    @change="
                      setResourcePermissionMode(
                        'spell',
                        spell.id,
                        ($event.target as HTMLSelectElement).value as
                          | 'open'
                          | 'restricted'
                      )
                    "
                    class="flex h-8 rounded-md border border-input bg-background px-2 py-1 text-xs text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                    @click.stop
                  >
                    <option value="open">Open</option>
                    <option value="restricted">Restricted</option>
                  </select>
                </div>
              </div>
              <div
                v-if="
                  getResourcePermissionMode('spell', spell.id) === 'restricted'
                "
                class="mt-2"
              >
                <Input
                  :model-value="resourcePermissions.spell?.[spell.id]?.error || ''"
                  @update:model-value="
                    setResourcePermissionMode(
                      'spell',
                      spell.id,
                      'restricted',
                      String($event ?? '')
                    )
                  "
                  placeholder="Default error message"
                  class="w-full text-xs"
                  @click.stop
                />
              </div>
            </div>
          </div>
        </Card>

        <!-- Permission Controls -->
        <Card class="p-6 border-2 shadow-xl bg-card/50 backdrop-blur-sm">
          <div class="mb-4">
            <Label class="text-base font-semibold">Permission Controls</Label>
            <p class="text-sm text-muted-foreground mt-1">
              Control whether each resource type requires permissions or is open
              to everyone
            </p>
          </div>
          <div class="space-y-6">
            <!-- Location Permission Mode -->
            <div>
              <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                  <MapPin class="h-5 w-5" />
                  <Label>Location Permission Mode</Label>
                </div>
                <div class="flex items-center space-x-4">
                  <label class="flex items-center">
                    <input
                      type="radio"
                      value="open"
                      v-model="formSettings.permission_mode_location"
                      class="h-4 w-4 text-primary border-gray-300 focus:ring-primary"
                    />
                    <span class="ml-2 text-sm">Open to Everyone</span>
                  </label>
                  <label class="flex items-center">
                    <input
                      type="radio"
                      value="restricted"
                      v-model="formSettings.permission_mode_location"
                      class="h-4 w-4 text-primary border-gray-300 focus:ring-primary"
                    />
                    <span class="ml-2 text-sm">Permission Required</span>
                  </label>
                </div>
              </div>
              <div
                v-if="formSettings.permission_mode_location === 'restricted'"
                class="mt-2"
              >
                <Label for="default_error_location"
                  >Default Error Message for Locations</Label
                >
                <Input
                  id="default_error_location"
                  v-model="formSettings.default_error_location"
                  placeholder="You do not have permission to use this location"
                  class="mt-2"
                />
              </div>
            </div>

            <!-- Node Permission Mode -->
            <div>
              <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                  <Network class="h-5 w-5" />
                  <Label>Node Permission Mode</Label>
                </div>
                <div class="flex items-center space-x-4">
                  <label class="flex items-center">
                    <input
                      type="radio"
                      value="open"
                      v-model="formSettings.permission_mode_node"
                      class="h-4 w-4 text-primary border-gray-300 focus:ring-primary"
                    />
                    <span class="ml-2 text-sm">Open to Everyone</span>
                  </label>
                  <label class="flex items-center">
                    <input
                      type="radio"
                      value="restricted"
                      v-model="formSettings.permission_mode_node"
                      class="h-4 w-4 text-primary border-gray-300 focus:ring-primary"
                    />
                    <span class="ml-2 text-sm">Permission Required</span>
                  </label>
                </div>
              </div>
              <div
                v-if="formSettings.permission_mode_node === 'restricted'"
                class="mt-2"
              >
                <Label for="default_error_node"
                  >Default Error Message for Nodes</Label
                >
                <Input
                  id="default_error_node"
                  v-model="formSettings.default_error_node"
                  placeholder="You do not have permission to use this node"
                  class="mt-2"
                />
              </div>
            </div>

            <!-- Realm Permission Mode -->
            <div>
              <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                  <Box class="h-5 w-5" />
                  <Label>Realm Permission Mode</Label>
                </div>
                <div class="flex items-center space-x-4">
                  <label class="flex items-center">
                    <input
                      type="radio"
                      value="open"
                      v-model="formSettings.permission_mode_realm"
                      class="h-4 w-4 text-primary border-gray-300 focus:ring-primary"
                    />
                    <span class="ml-2 text-sm">Open to Everyone</span>
                  </label>
                  <label class="flex items-center">
                    <input
                      type="radio"
                      value="restricted"
                      v-model="formSettings.permission_mode_realm"
                      class="h-4 w-4 text-primary border-gray-300 focus:ring-primary"
                    />
                    <span class="ml-2 text-sm">Permission Required</span>
                  </label>
                </div>
              </div>
              <div
                v-if="formSettings.permission_mode_realm === 'restricted'"
                class="mt-2"
              >
                <Label for="default_error_realm"
                  >Default Error Message for Realms</Label
                >
                <Input
                  id="default_error_realm"
                  v-model="formSettings.default_error_realm"
                  placeholder="You do not have permission to use this realm"
                  class="mt-2"
                />
              </div>
            </div>

            <!-- Spell Permission Mode -->
            <div>
              <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                  <Sparkles class="h-5 w-5" />
                  <Label>Spell Permission Mode</Label>
                </div>
                <div class="flex items-center space-x-4">
                  <label class="flex items-center">
                    <input
                      type="radio"
                      value="open"
                      v-model="formSettings.permission_mode_spell"
                      class="h-4 w-4 text-primary border-gray-300 focus:ring-primary"
                    />
                    <span class="ml-2 text-sm">Open to Everyone</span>
                  </label>
                  <label class="flex items-center">
                    <input
                      type="radio"
                      value="restricted"
                      v-model="formSettings.permission_mode_spell"
                      class="h-4 w-4 text-primary border-gray-300 focus:ring-primary"
                    />
                    <span class="ml-2 text-sm">Permission Required</span>
                  </label>
                </div>
              </div>
              <div
                v-if="formSettings.permission_mode_spell === 'restricted'"
                class="mt-2"
              >
                <Label for="default_error_spell"
                  >Default Error Message for Spells</Label
                >
                <Input
                  id="default_error_spell"
                  v-model="formSettings.default_error_spell"
                  placeholder="You do not have permission to use this spell"
                  class="mt-2"
                />
              </div>
            </div>
          </div>
        </Card>

        <!-- Save Button -->
        <div class="flex justify-end">
          <Button type="submit" :disabled="saving" size="lg">
            <Loader2 v-if="saving" class="h-4 w-4 mr-2 animate-spin" />
            <Save v-else class="h-4 w-4 mr-2" />
            Save Settings
          </Button>
        </div>
      </form>
    </div>
  </div>
</template>
