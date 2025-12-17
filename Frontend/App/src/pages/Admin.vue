<script setup lang="ts">
import { ref, onMounted, computed } from "vue";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
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

const loadingOptions = ref(false);

// Realm filter for spells
const selectedRealmFilter = ref<number | null>(null);

// Form state
const formSettings = ref<PluginSettings>({
  user_creation_enabled: false,
  allowed_locations: [],
  allowed_nodes: [],
  allowed_realms: [],
  allowed_spells: [],
});

// Filtered spells based on selected realm
const filteredSpells = computed(() => {
  if (selectedRealmFilter.value === null) {
    return allSpells.value;
  }
  return allSpells.value.filter(
    (s: { id: number; name: string; realm_id: number }) =>
      s.realm_id === selectedRealmFilter.value
  );
});

const loadSettings = async () => {
  try {
    settings.value = await getSettings();
    if (settings.value) {
      formSettings.value = { ...settings.value };
    }
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to load settings");
  }
};

const loadOptions = async () => {
  loadingOptions.value = true;
  try {
    const [locationsRes, nodesRes, realmsRes, spellsRes] = await Promise.all([
      axios.get("/api/admin/locations", { params: { limit: 1000 } }),
      axios.get("/api/admin/nodes", { params: { limit: 1000 } }),
      axios.get("/api/admin/realms", { params: { limit: 1000 } }),
      axios.get("/api/admin/spells", { params: { limit: 1000 } }),
    ]);

    allLocations.value = locationsRes.data?.data?.locations || [];
    allNodes.value = nodesRes.data?.data?.nodes || [];
    allRealms.value = realmsRes.data?.data?.realms || [];
    allSpells.value = spellsRes.data?.data?.spells || [];
  } catch (err) {
    const errorMsg =
      err instanceof Error ? err.message : "Failed to load options";
    const axiosError = err as AxiosError<{ message?: string }>;
    toast.error(axiosError?.response?.data?.message || errorMsg);
  } finally {
    loadingOptions.value = false;
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
    settings.value = await updateSettings(formSettings.value);
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
  <div class="w-full h-full overflow-auto p-4">
    <div class="container mx-auto max-w-7xl">
      <div class="mb-6">
        <h1 class="text-2xl font-semibold">New Server Settings</h1>
        <p class="text-sm text-muted-foreground">
          Configure user server creation options and restrictions
        </p>
      </div>

      <Card v-if="loading && !settings" class="p-6">
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
        <Card class="p-6">
          <div class="flex items-center justify-between">
            <div class="space-y-1">
              <Label class="text-base font-semibold"
                >Enable User Server Creation</Label
              >
              <p class="text-sm text-muted-foreground">
                Allow users to create new servers using their available
                resources
              </p>
            </div>
            <input
              type="checkbox"
              :checked="formSettings.user_creation_enabled"
              @change="
                formSettings.user_creation_enabled = (
                  $event.target as HTMLInputElement
                ).checked
              "
              class="h-5 w-5 rounded border-gray-300"
            />
          </div>
        </Card>

        <!-- Allowed Locations -->
        <Card class="p-6">
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
              @click="toggleLocation(location.id)"
              class="flex items-center gap-2 p-3 border rounded-lg cursor-pointer hover:bg-accent transition-colors"
              :class="{
                'border-primary bg-primary/10': isLocationSelected(location.id),
              }"
            >
              <div
                class="flex h-5 w-5 items-center justify-center rounded border"
                :class="{
                  'bg-primary border-primary': isLocationSelected(location.id),
                }"
              >
                <Check
                  v-if="isLocationSelected(location.id)"
                  class="h-4 w-4 text-primary-foreground"
                />
              </div>
              <span class="font-medium">{{ location.name }}</span>
            </div>
          </div>
        </Card>

        <!-- Allowed Nodes -->
        <Card class="p-6">
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
              Select which nodes users can use. Leave empty to allow all nodes.
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
              v-for="node in allNodes"
              :key="node.id"
              @click="toggleNode(node.id)"
              class="flex items-center gap-2 p-3 border rounded-lg cursor-pointer hover:bg-accent transition-colors"
              :class="{
                'border-primary bg-primary/10': isNodeSelected(node.id),
              }"
            >
              <div
                class="flex h-5 w-5 items-center justify-center rounded border"
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
          </div>
        </Card>

        <!-- Allowed Realms -->
        <Card class="p-6">
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
              @click="toggleRealm(realm.id)"
              class="flex items-center gap-2 p-3 border rounded-lg cursor-pointer hover:bg-accent transition-colors"
              :class="{
                'border-primary bg-primary/10': isRealmSelected(realm.id),
              }"
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
          </div>
        </Card>

        <!-- Allowed Spells -->
        <Card class="p-6">
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
                class="w-full md:w-64 px-3 py-2 border rounded-lg bg-background text-foreground"
              >
                <option :value="null">All Realms</option>
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
              @click="toggleSpell(spell.id)"
              class="flex items-center gap-2 p-3 border rounded-lg cursor-pointer hover:bg-accent transition-colors"
              :class="{
                'border-primary bg-primary/10': isSpellSelected(spell.id),
              }"
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
