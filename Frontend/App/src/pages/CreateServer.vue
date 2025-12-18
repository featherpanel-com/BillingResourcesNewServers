<script setup lang="ts">
import { ref, onMounted, computed, watch } from "vue";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
} from "@/components/ui/command";
import { cn } from "@/lib/utils";
import {
  Loader2,
  Server,
  Plus,
  HardDrive,
  Cpu,
  Database,
  AlertCircle,
  CheckCircle2,
  ChevronsUpDown,
  Check,
} from "lucide-vue-next";
import {
  useNewServerAPI,
  type Location as LocationType,
  type Node,
  type Realm,
  type Spell,
  type CreateServerData,
  type ServerCreationOptions,
} from "@/composables/useNewServerAPI";
import { useToast } from "vue-toastification";

const toast = useToast();
const { loading, error, getOptions, getSpellDetails, createServer } =
  useNewServerAPI();

const options = ref<ServerCreationOptions | null>(null);

// Docker image selection
const dockerImagePopoverOpen = ref(false);
const availableDockerImages = ref<string[]>([]);
const selectedDockerImage = ref<string>("");

const form = ref<CreateServerData>({
  name: "",
  node_id: 0,
  realms_id: 0,
  spell_id: 0,
  allocation_id: 0,
  memory: 1024,
  cpu: 0, // Will be set based on available resources
  disk: 2048,
  swap: 0,
  io: 500,
  description: "",
  startup: "",
  image: "",
  database_limit: 0,
  allocation_limit: 0,
  backup_limit: 0,
  variables: {},
});

const creating = ref(false);

// Filtered options based on selections
const filteredNodes = computed(() => {
  if (!options.value) return [];

  // If a location is selected, filter nodes by location
  if (form.value.location_id && form.value.location_id > 0) {
    return options.value.nodes.filter(
      (n) => n.location_id === form.value.location_id
    );
  }

  // Otherwise show all available nodes
  return options.value.nodes;
});

const filteredSpells = computed(() => {
  if (!options.value || !form.value.realms_id) {
    return [];
  }
  return options.value.spells.filter(
    (s) => s.realm_id === form.value.realms_id
  );
});

// Allocations are auto-selected on the backend during server creation

// Watch for options changes to adjust CPU default
watch(
  () => options.value?.available_resources.cpu_limit,
  (cpuLimit) => {
    if (cpuLimit !== undefined && form.value.cpu > cpuLimit) {
      form.value.cpu = Math.min(form.value.cpu, cpuLimit);
    }
  }
);

// Watch for realm changes to reset spell
watch(
  () => form.value.realms_id,
  () => {
    form.value.spell_id = 0;
  }
);

const formatBytes = (mb: number): string => {
  if (mb === 0) return "0 MB";
  if (mb >= 1024) {
    return `${(mb / 1024).toFixed(2)} GB`;
  }
  return `${mb} MB`;
};

const formatPercentage = (value: number): string => {
  return `${value}%`;
};

const loadOptions = async () => {
  try {
    const data = await getOptions();
    options.value = data;
    // Set default values based on minimum resources
    if (data.minimum_resources) {
      form.value.memory = Math.max(
        form.value.memory,
        data.minimum_resources.memory
      );
      form.value.cpu = Math.max(form.value.cpu, data.minimum_resources.cpu);
      form.value.disk = Math.max(form.value.disk, data.minimum_resources.disk);
    }
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to load options");
  }
};

// Allocations are auto-selected on the backend during server creation

const selectLocation = (location: LocationType) => {
  // Don't allow selection of disabled locations
  if (location.allowed === false) {
    toast.error(
      location.error_message ||
        "You do not have permission to use this location"
    );
    return;
  }
  form.value.location_id = location.id;
  // Reset node selection when location changes
  form.value.node_id = 0;
};

const selectNode = (node: Node) => {
  // Don't allow selection of disabled nodes
  if (node.allowed === false) {
    toast.error(
      node.error_message || "You do not have permission to use this node"
    );
    return;
  }
  form.value.node_id = node.id;
};

const selectRealm = (realm: Realm) => {
  // Don't allow selection of disabled realms
  if (realm.allowed === false) {
    toast.error(
      realm.error_message || "You do not have permission to use this realm"
    );
    return;
  }
  form.value.realms_id = realm.id;
  // Reset spell selection when realm changes
  form.value.spell_id = 0;
};

const selectSpell = async (spell: Spell) => {
  // Don't allow selection of disabled spells
  if (spell.allowed === false) {
    toast.error(
      spell.error_message || "You do not have permission to use this spell"
    );
    return;
  }
  form.value.spell_id = spell.id;
  // Load spell details to get default startup and image (using user API)
  try {
    const spellData = await getSpellDetails(spell.id);
    if (spellData) {
      // Update startup command from spell
      if (spellData.startup) {
        form.value.startup = spellData.startup;
      }

      // Parse docker images (like admin page)
      if (spellData.docker_images) {
        try {
          const dockerImagesObj = JSON.parse(spellData.docker_images);
          availableDockerImages.value = Object.values(dockerImagesObj);
          selectedDockerImage.value = availableDockerImages.value[0] || "";
          form.value.image = selectedDockerImage.value;
        } catch (e) {
          console.error("Failed to parse docker images:", e);
          availableDockerImages.value = [];
          selectedDockerImage.value = "";
          // If parsing fails, try docker_image field as fallback
          if (spellData.docker_image) {
            selectedDockerImage.value = spellData.docker_image;
            form.value.image = spellData.docker_image;
          }
        }
      } else if (spellData.docker_image) {
        // Fallback to docker_image field
        selectedDockerImage.value = spellData.docker_image;
        form.value.image = spellData.docker_image;
        availableDockerImages.value = [spellData.docker_image];
      } else {
        availableDockerImages.value = [];
        selectedDockerImage.value = "";
        form.value.image = "";
      }
    }
  } catch (err) {
    // Ignore errors, just use defaults
    console.error("Failed to fetch spell details:", err);
    availableDockerImages.value = [];
    selectedDockerImage.value = "";
  }
};

const selectDockerImage = (image: string) => {
  selectedDockerImage.value = image;
  form.value.image = image;
  dockerImagePopoverOpen.value = false;
};

// Allocation selection removed - allocations are auto-selected on the backend

const canCreate = computed(() => {
  if (!options.value) return false;
  const available = options.value.available_resources;
  const dbLimit = form.value.database_limit ?? 0;
  const allocLimit = form.value.allocation_limit ?? 0;
  const backupLimit = form.value.backup_limit ?? 0;

  return (
    form.value.name.trim() !== "" &&
    form.value.node_id > 0 &&
    form.value.realms_id > 0 &&
    form.value.spell_id > 0 &&
    form.value.startup.trim() !== "" &&
    form.value.image.trim() !== "" &&
    form.value.memory >= (options.value?.minimum_resources?.memory ?? 128) &&
    form.value.cpu >= (options.value?.minimum_resources?.cpu ?? 0) &&
    form.value.disk >= (options.value?.minimum_resources?.disk ?? 128) &&
    form.value.memory <= available.memory_limit &&
    form.value.cpu <= available.cpu_limit &&
    form.value.disk <= available.disk_limit &&
    available.server_limit > 0 &&
    dbLimit >= 0 &&
    allocLimit >= 0 &&
    backupLimit >= 0 &&
    (available.database_limit === 0 || dbLimit <= available.database_limit) &&
    (available.allocation_limit === 0 ||
      allocLimit <= available.allocation_limit) &&
    (available.backup_limit === 0 || backupLimit <= available.backup_limit)
  );
});

const handleCreate = async () => {
  if (!canCreate.value) {
    // Provide more specific error messages
    if (!options.value) {
      toast.error("Options not loaded yet");
      return;
    }
    const available = options.value.available_resources;

    if (form.value.name.trim() === "") {
      toast.error("Server name is required");
      return;
    }
    if (form.value.node_id <= 0) {
      toast.error("Please select a node");
      return;
    }
    if (form.value.realms_id <= 0) {
      toast.error("Please select a realm");
      return;
    }
    if (form.value.spell_id <= 0) {
      toast.error("Please select a spell");
      return;
    }
    if (form.value.startup.trim() === "") {
      toast.error("Startup command is required");
      return;
    }
    if (form.value.image.trim() === "") {
      toast.error("Docker image is required");
      return;
    }
    if (form.value.memory < 128) {
      toast.error("Memory must be at least 128 MB");
      return;
    }
    if (form.value.memory > available.memory_limit) {
      toast.error(
        `Memory exceeds limit. Max: ${formatBytes(available.memory_limit)}`
      );
      return;
    }
    const minCpu = options.value.minimum_resources?.cpu ?? 0;
    if (form.value.cpu < minCpu) {
      toast.error(`CPU must be at least ${minCpu}%`);
      return;
    }
    if (form.value.cpu > available.cpu_limit) {
      toast.error(
        `CPU exceeds limit. Max: ${formatPercentage(available.cpu_limit)}`
      );
      return;
    }
    const minDisk = options.value.minimum_resources?.disk ?? 128;
    if (form.value.disk < minDisk) {
      toast.error(`Disk must be at least ${minDisk} MB`);
      return;
    }
    if (form.value.disk > available.disk_limit) {
      toast.error(
        `Disk exceeds limit. Max: ${formatBytes(available.disk_limit)}`
      );
      return;
    }
    if (available.server_limit <= 0) {
      toast.error("You have reached your server limit");
      return;
    }
    const dbLimit = form.value.database_limit ?? 0;
    const allocLimit = form.value.allocation_limit ?? 0;
    const backupLimit = form.value.backup_limit ?? 0;
    if (available.database_limit > 0 && dbLimit > available.database_limit) {
      toast.error(
        `Database limit exceeds available. Max: ${available.database_limit}`
      );
      return;
    }
    if (
      available.allocation_limit > 0 &&
      allocLimit > available.allocation_limit
    ) {
      toast.error(
        `Allocation limit exceeds available. Max: ${available.allocation_limit}`
      );
      return;
    }
    if (available.backup_limit > 0 && backupLimit > available.backup_limit) {
      toast.error(
        `Backup limit exceeds available. Max: ${available.backup_limit}`
      );
      return;
    }

    toast.error(
      "Please fill in all required fields and ensure resources are within limits"
    );
    return;
  }

  creating.value = true;
  try {
    await createServer(form.value);
    toast.success("Server created successfully!");
    // Reset form
    form.value = {
      name: "",
      node_id: 0,
      realms_id: 0,
      spell_id: 0,
      allocation_id: 0, // Will be auto-selected on backend
      memory: 1024,
      cpu: Math.min(100, options.value?.available_resources.cpu_limit ?? 100),
      disk: 2048,
      swap: 0,
      io: 500,
      description: "",
      startup: "",
      image: "",
      database_limit: 0,
      allocation_limit: 0,
      backup_limit: 0,
      variables: {},
    };
    // Reload options to refresh available resources
    await loadOptions();
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to create server");
  } finally {
    creating.value = false;
  }
};

onMounted(() => {
  loadOptions();
});
</script>

<template>
  <div class="min-h-screen p-4 md:p-8">
    <div class="max-w-5xl mx-auto space-y-8">
      <!-- Header Section -->
      <div class="text-center space-y-4">
        <div class="flex items-center justify-center gap-3">
          <div class="relative">
            <div
              class="absolute inset-0 bg-primary/20 blur-2xl rounded-full"
            ></div>
            <Server class="relative h-12 w-12 text-primary" />
          </div>
        </div>
        <div>
          <h1
            class="text-5xl font-bold bg-gradient-to-r from-primary to-primary/60 bg-clip-text text-transparent"
          >
            Create New Server
          </h1>
          <p class="text-lg text-muted-foreground mt-2">
            Create a new server using your available resources
          </p>
        </div>
      </div>

      <Card
        v-if="loading && !options"
        class="p-8 md:p-10 border-2 shadow-xl bg-card/50 backdrop-blur-sm"
      >
        <div class="flex items-center justify-center py-12">
          <Loader2 class="h-8 w-8 animate-spin text-primary" />
        </div>
      </Card>

      <Card
        v-else-if="error"
        class="p-8 md:p-10 border-2 border-destructive/50 bg-destructive/5"
      >
        <div class="flex items-center gap-3">
          <AlertCircle class="h-6 w-6 text-destructive" />
          <div>
            <h3 class="font-semibold text-destructive">Error</h3>
            <p class="text-sm text-muted-foreground">{{ error }}</p>
          </div>
        </div>
      </Card>

      <div v-else-if="options" class="space-y-6">
        <!-- Available Resources Summary -->
        <Card
          class="p-8 md:p-10 border-2 shadow-xl bg-card/50 backdrop-blur-sm"
        >
          <div class="space-y-4">
            <div class="flex items-center gap-3 mb-4">
              <div class="p-2 rounded-lg bg-primary/10">
                <CheckCircle2 class="h-6 w-6 text-primary" />
              </div>
              <div>
                <h2 class="text-2xl font-bold">Available Resources</h2>
                <p class="text-sm text-muted-foreground">
                  Resources available for server creation
                </p>
              </div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div class="p-4 border rounded-lg">
                <div class="flex items-center gap-2 mb-2">
                  <HardDrive class="h-4 w-4 text-muted-foreground" />
                  <span class="text-sm text-muted-foreground">Memory</span>
                </div>
                <div class="text-xl font-bold">
                  {{ formatBytes(options.available_resources.memory_limit) }}
                </div>
              </div>
              <div class="p-4 border rounded-lg">
                <div class="flex items-center gap-2 mb-2">
                  <Cpu class="h-4 w-4 text-muted-foreground" />
                  <span class="text-sm text-muted-foreground">CPU</span>
                </div>
                <div class="text-xl font-bold">
                  {{ formatPercentage(options.available_resources.cpu_limit) }}
                </div>
              </div>
              <div class="p-4 border rounded-lg">
                <div class="flex items-center gap-2 mb-2">
                  <Database class="h-4 w-4 text-muted-foreground" />
                  <span class="text-sm text-muted-foreground">Disk</span>
                </div>
                <div class="text-xl font-bold">
                  {{ formatBytes(options.available_resources.disk_limit) }}
                </div>
              </div>
              <div class="p-4 border rounded-lg">
                <div class="flex items-center gap-2 mb-2">
                  <Server class="h-4 w-4 text-muted-foreground" />
                  <span class="text-sm text-muted-foreground">Servers</span>
                </div>
                <div class="text-xl font-bold">
                  {{ options.available_resources.server_limit }}
                </div>
              </div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mt-4">
              <div class="p-4 border rounded-lg">
                <div class="flex items-center gap-2 mb-2">
                  <Database class="h-4 w-4 text-muted-foreground" />
                  <span class="text-sm text-muted-foreground">Databases</span>
                </div>
                <div class="text-xl font-bold">
                  {{
                    options.available_resources.database_limit === 0
                      ? "∞"
                      : options.available_resources.database_limit
                  }}
                </div>
              </div>
              <div class="p-4 border rounded-lg">
                <div class="flex items-center gap-2 mb-2">
                  <Server class="h-4 w-4 text-muted-foreground" />
                  <span class="text-sm text-muted-foreground">Allocations</span>
                </div>
                <div class="text-xl font-bold">
                  {{
                    options.available_resources.allocation_limit === 0
                      ? "∞"
                      : options.available_resources.allocation_limit
                  }}
                </div>
              </div>
              <div class="p-4 border rounded-lg">
                <div class="flex items-center gap-2 mb-2">
                  <Database class="h-4 w-4 text-muted-foreground" />
                  <span class="text-sm text-muted-foreground">Backups</span>
                </div>
                <div class="text-xl font-bold">
                  {{
                    options.available_resources.backup_limit === 0
                      ? "∞"
                      : options.available_resources.backup_limit
                  }}
                </div>
              </div>
            </div>
          </div>
        </Card>

        <!-- Server Creation Form -->
        <Card
          class="p-8 md:p-10 border-2 shadow-xl bg-card/50 backdrop-blur-sm"
        >
          <div class="space-y-6">
            <div class="flex items-center gap-3 mb-4">
              <div class="p-2 rounded-lg bg-primary/10">
                <Server class="h-6 w-6 text-primary" />
              </div>
              <div>
                <h2 class="text-2xl font-bold">Server Configuration</h2>
                <p class="text-sm text-muted-foreground">
                  Configure your new server settings
                </p>
              </div>
            </div>

            <div class="space-y-6">
              <!-- Server Name -->
              <div>
                <Label for="name">Server Name *</Label>
                <Input
                  id="name"
                  v-model="form.name"
                  placeholder="My Awesome Server"
                  class="mt-2"
                />
              </div>

              <!-- Description -->
              <div>
                <Label for="description">Description</Label>
                <Input
                  id="description"
                  v-model="form.description"
                  placeholder="Optional server description"
                  class="mt-2"
                />
              </div>

              <!-- Location Selection -->
              <div>
                <Label>Location *</Label>
                <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-2">
                  <div
                    v-for="location in options.locations"
                    :key="location.id"
                    @click="selectLocation(location)"
                    class="p-3 border rounded-lg cursor-pointer hover:bg-accent transition-colors"
                    :class="{
                      'border-primary bg-primary/10':
                        form.location_id && form.location_id === location.id,
                    }"
                  >
                    <div class="font-medium">{{ location.name }}</div>
                    <div
                      v-if="location.description"
                      class="text-sm text-muted-foreground"
                    >
                      {{ location.description }}
                    </div>
                  </div>
                </div>
              </div>

              <!-- Node Selection -->
              <div v-if="form.location_id && form.location_id > 0">
                <Label>Node *</Label>
                <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-2">
                  <div
                    v-for="node in filteredNodes"
                    :key="node.id"
                    @click="selectNode(node)"
                    class="p-3 border rounded-lg transition-colors"
                    :class="{
                      'border-primary bg-primary/10': form.node_id === node.id,
                      'cursor-pointer hover:bg-accent': node.allowed !== false,
                      'cursor-not-allowed opacity-50 bg-muted':
                        node.allowed === false,
                    }"
                  >
                    <div class="flex items-center justify-between">
                      <div class="font-medium">{{ node.name }}</div>
                      <AlertCircle
                        v-if="node.allowed === false"
                        class="h-4 w-4 text-destructive"
                      />
                    </div>
                    <div class="text-sm text-muted-foreground">
                      {{ node.fqdn }}
                    </div>
                    <div
                      v-if="node.allowed === false && node.error_message"
                      class="text-xs text-destructive mt-1"
                    >
                      {{ node.error_message }}
                    </div>
                  </div>
                </div>
                <p
                  v-if="filteredNodes.length === 0"
                  class="text-sm text-muted-foreground mt-2"
                >
                  No nodes available in this location
                </p>
              </div>

              <!-- Realm Selection -->
              <div>
                <Label>Realm *</Label>
                <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-2">
                  <div
                    v-for="realm in options.realms"
                    :key="realm.id"
                    @click="selectRealm(realm)"
                    class="p-3 border rounded-lg transition-colors"
                    :class="{
                      'border-primary bg-primary/10':
                        form.realms_id === realm.id,
                      'cursor-pointer hover:bg-accent': realm.allowed !== false,
                      'cursor-not-allowed opacity-50 bg-muted':
                        realm.allowed === false,
                    }"
                  >
                    <div class="flex items-center justify-between">
                      <div class="font-medium">{{ realm.name }}</div>
                      <AlertCircle
                        v-if="realm.allowed === false"
                        class="h-4 w-4 text-destructive"
                      />
                    </div>
                    <div
                      v-if="realm.description"
                      class="text-sm text-muted-foreground"
                    >
                      {{ realm.description }}
                    </div>
                    <div
                      v-if="realm.allowed === false && realm.error_message"
                      class="text-xs text-destructive mt-1"
                    >
                      {{ realm.error_message }}
                    </div>
                  </div>
                </div>
              </div>

              <!-- Spell Selection -->
              <div v-if="form.realms_id > 0">
                <Label>Spell *</Label>
                <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-2">
                  <div
                    v-for="spell in filteredSpells"
                    :key="spell.id"
                    @click="selectSpell(spell)"
                    class="p-3 border rounded-lg transition-colors"
                    :class="{
                      'border-primary bg-primary/10':
                        form.spell_id === spell.id,
                      'cursor-pointer hover:bg-accent': spell.allowed !== false,
                      'cursor-not-allowed opacity-50 bg-muted':
                        spell.allowed === false,
                    }"
                  >
                    <div class="flex items-center justify-between">
                      <div class="font-medium">{{ spell.name }}</div>
                      <AlertCircle
                        v-if="spell.allowed === false"
                        class="h-4 w-4 text-destructive"
                      />
                    </div>
                    <div
                      v-if="spell.description"
                      class="text-sm text-muted-foreground"
                    >
                      {{ spell.description }}
                    </div>
                    <div
                      v-if="spell.allowed === false && spell.error_message"
                      class="text-xs text-destructive mt-1"
                    >
                      {{ spell.error_message }}
                    </div>
                  </div>
                </div>
              </div>

              <!-- Resources -->
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <Label for="memory">Memory (MB) *</Label>
                  <Input
                    id="memory"
                    v-model.number="form.memory"
                    type="number"
                    min="128"
                    :max="options.available_resources.memory_limit"
                    class="mt-2"
                  />
                  <p class="text-xs text-muted-foreground mt-1">
                    Max:
                    {{ formatBytes(options.available_resources.memory_limit) }}
                  </p>
                </div>
                <div>
                  <Label for="cpu">CPU (%) *</Label>
                  <Input
                    id="cpu"
                    v-model.number="form.cpu"
                    type="number"
                    min="0"
                    :max="options.available_resources.cpu_limit"
                    class="mt-2"
                  />
                  <p class="text-xs text-muted-foreground mt-1">
                    Max:
                    {{
                      formatPercentage(options.available_resources.cpu_limit)
                    }}
                  </p>
                </div>
                <div>
                  <Label for="disk">Disk (MB) *</Label>
                  <Input
                    id="disk"
                    v-model.number="form.disk"
                    type="number"
                    min="128"
                    :max="options.available_resources.disk_limit"
                    class="mt-2"
                  />
                  <p class="text-xs text-muted-foreground mt-1">
                    Max:
                    {{ formatBytes(options.available_resources.disk_limit) }}
                  </p>
                </div>
              </div>

              <!-- Startup Command -->
              <div v-if="form.spell_id > 0">
                <Label for="startup">Startup Command *</Label>
                <Input
                  id="startup"
                  v-model="form.startup"
                  placeholder="java -jar server.jar"
                  class="mt-2"
                />
              </div>

              <!-- Docker Image Selection -->
              <div v-if="form.spell_id > 0 && availableDockerImages.length > 0">
                <Label for="docker-image">Docker Image *</Label>
                <Popover v-model:open="dockerImagePopoverOpen">
                  <PopoverTrigger as-child>
                    <Button
                      variant="outline"
                      role="combobox"
                      :aria-expanded="dockerImagePopoverOpen"
                      class="w-full justify-between mt-2"
                    >
                      {{ selectedDockerImage || "Select Docker image..." }}
                      <ChevronsUpDown
                        class="ml-2 h-4 w-4 shrink-0 opacity-50"
                      />
                    </Button>
                  </PopoverTrigger>
                  <PopoverContent class="w-[400px] p-0">
                    <Command>
                      <CommandInput placeholder="Search Docker images..." />
                      <CommandEmpty>No Docker image found.</CommandEmpty>
                      <CommandGroup>
                        <CommandItem
                          v-for="image in availableDockerImages"
                          :key="image"
                          :value="image"
                          @select="selectDockerImage(image)"
                        >
                          <Check
                            :class="
                              cn(
                                'mr-2 h-4 w-4',
                                selectedDockerImage === image
                                  ? 'opacity-100'
                                  : 'opacity-0'
                              )
                            "
                          />
                          <div>
                            <div class="font-medium">{{ image }}</div>
                            <div class="text-xs text-muted-foreground">
                              Docker image for this spell
                            </div>
                          </div>
                        </CommandItem>
                      </CommandGroup>
                    </Command>
                  </PopoverContent>
                </Popover>
                <p class="text-xs text-muted-foreground mt-1">
                  Select the Docker image for this spell. This will be used to
                  deploy the server.
                </p>
              </div>
              <!-- Fallback if no docker images available -->
              <div
                v-else-if="
                  form.spell_id > 0 && availableDockerImages.length === 0
                "
              >
                <Label for="image">Docker Image *</Label>
                <Input
                  id="image"
                  v-model="form.image"
                  placeholder="quay.io/pterodactyl/core:java"
                  class="mt-2"
                />
                <p class="text-xs text-muted-foreground mt-1">
                  Enter the Docker image for this spell.
                </p>
              </div>

              <!-- Feature Limits -->
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <Label for="database_limit">Database Limit</Label>
                  <Input
                    id="database_limit"
                    v-model.number="form.database_limit"
                    type="number"
                    min="0"
                    :max="
                      options.available_resources.database_limit === 0
                        ? undefined
                        : options.available_resources.database_limit
                    "
                    placeholder="0"
                    class="mt-2"
                  />
                  <p class="text-xs text-muted-foreground mt-1">
                    Maximum number of databases for this server
                    <span v-if="options.available_resources.database_limit > 0">
                      (Max available:
                      {{ options.available_resources.database_limit }})
                    </span>
                    <span v-else> (Unlimited) </span>
                  </p>
                </div>
                <div>
                  <Label for="allocation_limit">Allocation Limit</Label>
                  <Input
                    id="allocation_limit"
                    v-model.number="form.allocation_limit"
                    type="number"
                    min="0"
                    :max="
                      options.available_resources.allocation_limit === 0
                        ? undefined
                        : options.available_resources.allocation_limit
                    "
                    placeholder="0"
                    class="mt-2"
                  />
                  <p class="text-xs text-muted-foreground mt-1">
                    Maximum number of allocations for this server
                    <span
                      v-if="options.available_resources.allocation_limit > 0"
                    >
                      (Max available:
                      {{ options.available_resources.allocation_limit }})
                    </span>
                    <span v-else> (Unlimited) </span>
                  </p>
                </div>
                <div>
                  <Label for="backup_limit">Backup Limit</Label>
                  <Input
                    id="backup_limit"
                    v-model.number="form.backup_limit"
                    type="number"
                    min="0"
                    :max="
                      options.available_resources.backup_limit === 0
                        ? undefined
                        : options.available_resources.backup_limit
                    "
                    placeholder="0"
                    class="mt-2"
                  />
                  <p class="text-xs text-muted-foreground mt-1">
                    Maximum number of backups for this server
                    <span v-if="options.available_resources.backup_limit > 0">
                      (Max available:
                      {{ options.available_resources.backup_limit }})
                    </span>
                    <span v-else> (Unlimited) </span>
                  </p>
                </div>
              </div>
            </div>
          </div>
        </Card>

        <!-- Create Button -->
        <div class="flex justify-end">
          <Button @click="handleCreate" :disabled="creating" size="lg">
            <Loader2 v-if="creating" class="h-4 w-4 mr-2 animate-spin" />
            <Plus v-else class="h-4 w-4 mr-2" />
            Create Server
          </Button>
        </div>
      </div>
    </div>
  </div>
</template>
