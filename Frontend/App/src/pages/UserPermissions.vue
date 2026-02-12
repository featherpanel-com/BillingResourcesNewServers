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
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { cn } from "@/lib/utils";
import {
  Loader2,
  MapPin,
  Network,
  Box,
  Sparkles,
  Trash2,
  Plus,
  ChevronsUpDown,
  Check,
  Users,
  Edit,
  X as XIcon,
  Info,
  AlertCircle,
} from "lucide-vue-next";
import {
  useUserPermissionsAPI,
  type UserPermissionsData,
  type AddPermissionData,
} from "@/composables/useUserPermissionsAPI";
import {
  useGroupsAPI,
  type Group,
  type GroupWithPermissions,
  type CreateGroupData,
  type AddGroupPermissionData,
} from "@/composables/useGroupsAPI";
import { useToast } from "vue-toastification";
import axios from "axios";

const toast = useToast();

function getApiErrorMessage(err: unknown, fallback: string): string {
  if (axios.isAxiosError(err)) {
    const data = err.response?.data as
      | { error_message?: string; message?: string }
      | undefined;
    return data?.error_message || data?.message || err.message || fallback;
  }
  return err instanceof Error ? err.message : fallback;
}
const { loading, error, getUserPermissions, addPermission, deletePermission } =
  useUserPermissionsAPI();
const {
  loading: groupsLoading,
  getGroups,
  getGroup,
  getUserGroups,
  createGroup,
  updateGroup,
  deleteGroup,
  addGroupPermission,
  updateGroupPermission,
  deleteGroupPermission,
  setUserGroups,
} = useGroupsAPI();

// Active tab
const activeTab = ref<"users" | "groups">("users");

const userId = ref<number | null>(null);
const userSearch = ref("");
const selectedUser = ref<{
  id: number;
  username: string;
  email: string;
} | null>(null);
const permissions = ref<UserPermissionsData | null>(null);
const userSearchOpen = ref(false);
const allUsers = ref<Array<{ id: number; username: string; email: string }>>(
  []
);

// Add permission form
const showAddForm = ref(false);
const addFormResourceType = ref<"location" | "node" | "realm" | "spell">(
  "location"
);
const addFormResourceId = ref<number | null>(null);
const addFormCustomError = ref("");
const addFormOpen = ref(false);

// User groups form
const showUserGroupsForm = ref(false);
const userSelectedGroups = ref<number[]>([]);

// Groups management state - defined early to avoid initialization errors
const groups = ref<Group[]>([]);
const selectedGroup = ref<GroupWithPermissions | null>(null);
const showCreateGroupForm = ref(false);
const showEditGroupForm = ref(false);
const showGroupPermissionsForm = ref(false);
const editingGroupId = ref<number | null>(null);

// Group form state
const groupFormName = ref("");
const groupFormDescription = ref("");
const groupFormColor = ref("#3B82F6");
const groupFormPriority = ref(0);

// Group permission form state
const groupPermissionResourceType = ref<
  "location" | "node" | "realm" | "spell"
>("location");
const groupPermissionResourceId = ref<number | null>(null);
const groupPermissionCustomError = ref("");
const groupPermissionOpen = ref(false);

// Edit permission state
const editingPermission = ref<{
  resourceType: "location" | "node" | "realm" | "spell";
  resourceId: number;
  customErrorMessage: string;
} | null>(null);
const editPermissionCustomError = ref("");

// Available resources
const allLocations = ref<
  Array<{ id: number; name: string; description?: string }>
>([]);
const allNodes = ref<
  Array<{ id: number; name: string; location_id: number; fqdn?: string }>
>([]);
const allRealms = ref<
  Array<{ id: number; name: string; description?: string }>
>([]);
const allSpells = ref<
  Array<{ id: number; name: string; realm_id: number; description?: string }>
>([]);

const loadingResources = ref(false);

const filteredUsers = computed(() => {
  if (!userSearch.value) return allUsers.value.slice(0, 10);
  const search = userSearch.value.toLowerCase();
  return allUsers.value
    .filter(
      (u) =>
        u.username.toLowerCase().includes(search) ||
        u.email.toLowerCase().includes(search)
    )
    .slice(0, 10);
});

const filteredResources = computed(() => {
  let resources: Array<{ id: number; name: string; description: string }> = [];

  switch (addFormResourceType.value) {
    case "location":
      resources = allLocations.value.map((l) => ({
        id: l.id,
        name: l.name,
        description: l.description || l.name,
      }));
      break;
    case "node":
      resources = allNodes.value.map((n) => ({
        id: n.id,
        name: n.name,
        description: n.fqdn || n.name,
      }));
      break;
    case "realm":
      resources = allRealms.value.map((r) => ({
        id: r.id,
        name: r.name,
        description: r.description || r.name,
      }));
      break;
    case "spell":
      resources = allSpells.value.map((s) => ({
        id: s.id,
        name: s.name,
        description: s.description || s.name,
      }));
      break;
  }

  return resources;
});

const filteredGroupResources = computed(() => {
  let resources: Array<{ id: number; name: string; description: string }> = [];

  switch (groupPermissionResourceType.value) {
    case "location":
      resources = allLocations.value.map((l) => ({
        id: l.id,
        name: l.name,
        description: l.description || l.name,
      }));
      break;
    case "node":
      resources = allNodes.value.map((n) => ({
        id: n.id,
        name: n.name,
        description: n.fqdn || n.name,
      }));
      break;
    case "realm":
      resources = allRealms.value.map((r) => ({
        id: r.id,
        name: r.name,
        description: r.description || r.name,
      }));
      break;
    case "spell":
      resources = allSpells.value.map((s) => ({
        id: s.id,
        name: s.name,
        description: s.description || s.name,
      }));
      break;
  }

  return resources;
});

const loadUsers = async () => {
  try {
    const response = await axios.get("/api/admin/users", {
      params: { limit: 1000 },
    });
    allUsers.value = response.data?.data?.users || [];
  } catch (err) {
    toast.error(getApiErrorMessage(err, "Failed to load users"));
  }
};

const loadResources = async () => {
  loadingResources.value = true;
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
    toast.error(getApiErrorMessage(err, "Failed to load resources"));
  } finally {
    loadingResources.value = false;
  }
};

const selectUser = (user: { id: number; username: string; email: string }) => {
  selectedUser.value = user;
  userId.value = user.id;
  userSearchOpen.value = false;
  userSearch.value = "";
  loadUserPermissions();
};

const loadUserPermissions = async () => {
  if (!userId.value) return;
  try {
    permissions.value = await getUserPermissions(userId.value);
    // Load user's groups
    try {
      // Always fetch fresh groups list to ensure we have the latest data
      const allGroupsList = await getGroups();
      // Update groups if empty (but don't access groups.value if it causes issues)
      try {
        if (groups.value.length === 0 && allGroupsList.length > 0) {
          groups.value = allGroupsList;
        }
      } catch {
        // If groups isn't initialized yet, just set it
        groups.value = allGroupsList;
      }
      userSelectedGroups.value = [];
      // Note: User groups are managed via setUserGroups API,
      // so we don't need to check group membership here
    } catch {
      // If can't load groups, just continue
    }
  } catch (err) {
    toast.error(getApiErrorMessage(err, "Failed to load permissions"));
  }
};

const handleSetUserGroups = async () => {
  if (!userId.value) return;
  try {
    await setUserGroups(userId.value, userSelectedGroups.value);
    toast.success("User groups updated successfully");
    showUserGroupsForm.value = false;
    await loadUserPermissions();
  } catch (err) {
    toast.error(getApiErrorMessage(err, "Failed to update user groups"));
  }
};

const toggleUserGroup = (groupId: number) => {
  const index = userSelectedGroups.value.indexOf(groupId);
  if (index > -1) {
    userSelectedGroups.value.splice(index, 1);
  } else {
    userSelectedGroups.value.push(groupId);
  }
};

const isUserGroupSelected = (groupId: number) => {
  return userSelectedGroups.value.includes(groupId);
};

const handleAddPermission = async () => {
  if (!userId.value || !addFormResourceId.value) {
    toast.error("Please select a resource");
    return;
  }

  try {
    const data: AddPermissionData = {
      resource_type: addFormResourceType.value,
      resource_id: addFormResourceId.value,
      custom_error_message: addFormCustomError.value.trim() || undefined,
    };

    await addPermission(userId.value, data);
    toast.success("Permission added successfully");
    await loadUserPermissions();
    // Reset form
    showAddForm.value = false;
    addFormResourceId.value = null;
    addFormCustomError.value = "";
    addFormOpen.value = false;
  } catch (err) {
    toast.error(getApiErrorMessage(err, "Failed to add permission"));
  }
};

const handleDeletePermission = async (permissionId: number) => {
  if (!userId.value) return;
  if (!confirm("Are you sure you want to delete this permission?")) return;

  try {
    await deletePermission(userId.value, permissionId);
    toast.success("Permission deleted successfully");
    await loadUserPermissions();
  } catch (err) {
    toast.error(getApiErrorMessage(err, "Failed to delete permission"));
  }
};

const getResourceName = (
  resourceType: "location" | "node" | "realm" | "spell",
  resourceId: number
): string => {
  switch (resourceType) {
    case "location":
      return (
        allLocations.value.find((l) => l.id === resourceId)?.name ||
        `Location #${resourceId}`
      );
    case "node":
      return (
        allNodes.value.find((n) => n.id === resourceId)?.name ||
        `Node #${resourceId}`
      );
    case "realm":
      return (
        allRealms.value.find((r) => r.id === resourceId)?.name ||
        `Realm #${resourceId}`
      );
    case "spell":
      return (
        allSpells.value.find((s) => s.id === resourceId)?.name ||
        `Spell #${resourceId}`
      );
    default:
      return `Resource #${resourceId}`;
  }
};

// Reset resource selection when resource type changes
watch(
  () => addFormResourceType.value,
  () => {
    addFormResourceId.value = null;
    addFormOpen.value = false;
  }
);

// Reset group permission resource selection when resource type changes
watch(
  () => groupPermissionResourceType.value,
  () => {
    groupPermissionResourceId.value = null;
    groupPermissionOpen.value = false;
  }
);

// Reset form when closing
watch(
  () => showAddForm.value,
  (newVal) => {
    if (!newVal) {
      addFormResourceId.value = null;
      addFormCustomError.value = "";
      addFormOpen.value = false;
    }
  }
);

const loadGroups = async () => {
  try {
    const groupsList = await getGroups();
    if (Array.isArray(groupsList)) {
      groups.value = groupsList;
    } else {
      groups.value = [];
    }
  } catch (err) {
    toast.error(getApiErrorMessage(err, "Failed to load groups"));
    groups.value = [];
  }
};

const loadGroupDetails = async (groupId: number) => {
  try {
    selectedGroup.value = await getGroup(groupId);
  } catch (err) {
    toast.error(getApiErrorMessage(err, "Failed to load group details"));
  }
};

const handleCreateGroup = async () => {
  if (!groupFormName.value.trim()) {
    toast.error("Group name is required");
    return;
  }

  try {
    const data: CreateGroupData = {
      name: groupFormName.value.trim(),
      description: groupFormDescription.value.trim() || undefined,
      color: groupFormColor.value || undefined,
      priority: groupFormPriority.value || 0,
    };
    await createGroup(data);
    toast.success("Group created successfully");
    await loadGroups();
    resetGroupForm();
  } catch (err) {
    toast.error(getApiErrorMessage(err, "Failed to create group"));
  }
};

const handleUpdateGroup = async () => {
  if (!editingGroupId.value || !groupFormName.value.trim()) {
    toast.error("Group name is required");
    return;
  }

  try {
    const data: CreateGroupData = {
      name: groupFormName.value.trim(),
      description: groupFormDescription.value.trim() || undefined,
      color: groupFormColor.value || undefined,
      priority: groupFormPriority.value || 0,
    };
    await updateGroup(editingGroupId.value, data);
    toast.success("Group updated successfully");
    await loadGroups();
    if (
      selectedGroup.value &&
      selectedGroup.value.id === editingGroupId.value
    ) {
      await loadGroupDetails(editingGroupId.value);
    }
    resetGroupForm();
  } catch (err) {
    toast.error(getApiErrorMessage(err, "Failed to update group"));
  }
};

const handleDeleteGroup = async (groupId: number) => {
  if (
    !confirm(
      "Are you sure you want to delete this group? This will remove all permissions and user assignments."
    )
  ) {
    return;
  }

  try {
    await deleteGroup(groupId);
    toast.success("Group deleted successfully");
    await loadGroups();
    if (selectedGroup.value?.id === groupId) {
      selectedGroup.value = null;
    }
  } catch (err) {
    toast.error(getApiErrorMessage(err, "Failed to delete group"));
  }
};

const handleAddGroupPermission = async () => {
  if (!selectedGroup.value || !groupPermissionResourceId.value) {
    toast.error("Please select a resource");
    return;
  }

  try {
    const data: AddGroupPermissionData = {
      resource_type: groupPermissionResourceType.value,
      resource_id: groupPermissionResourceId.value,
      custom_error_message:
        groupPermissionCustomError.value.trim() || undefined,
    };
    await addGroupPermission(selectedGroup.value.id, data);
    toast.success("Permission added successfully");
    await loadGroupDetails(selectedGroup.value.id);
    resetGroupPermissionForm();
  } catch (err) {
    toast.error(getApiErrorMessage(err, "Failed to add permission"));
  }
};

const handleEditGroupPermission = (
  resourceType: "location" | "node" | "realm" | "spell",
  resourceId: number,
  customErrorMessage?: string
) => {
  editingPermission.value = {
    resourceType,
    resourceId,
    customErrorMessage: customErrorMessage || "",
  };
  editPermissionCustomError.value = customErrorMessage || "";
};

const handleUpdateGroupPermission = async () => {
  if (!selectedGroup.value || !editingPermission.value) return;

  try {
    await updateGroupPermission(
      selectedGroup.value.id,
      editingPermission.value.resourceType,
      editingPermission.value.resourceId,
      {
        custom_error_message:
          editPermissionCustomError.value.trim() || undefined,
      }
    );
    toast.success("Permission updated successfully");
    editingPermission.value = null;
    editPermissionCustomError.value = "";
    await loadGroupDetails(selectedGroup.value.id);
  } catch (err) {
    toast.error(getApiErrorMessage(err, "Failed to update permission"));
  }
};

const handleDeleteGroupPermission = async (
  resourceType: "location" | "node" | "realm" | "spell",
  resourceId: number
) => {
  if (!selectedGroup.value) return;
  if (!confirm("Are you sure you want to delete this permission?")) return;

  try {
    await deleteGroupPermission(
      selectedGroup.value.id,
      resourceType,
      resourceId
    );
    toast.success("Permission deleted successfully");
    await loadGroupDetails(selectedGroup.value.id);
  } catch (err) {
    toast.error(getApiErrorMessage(err, "Failed to delete permission"));
  }
};

const resetGroupForm = () => {
  groupFormName.value = "";
  groupFormDescription.value = "";
  groupFormColor.value = "#3B82F6";
  groupFormPriority.value = 0;
  showCreateGroupForm.value = false;
  showEditGroupForm.value = false;
  editingGroupId.value = null;
};

const resetGroupPermissionForm = () => {
  groupPermissionResourceType.value = "location";
  groupPermissionResourceId.value = null;
  groupPermissionCustomError.value = "";
  groupPermissionOpen.value = false;
  showGroupPermissionsForm.value = false;
};

const openEditGroupForm = (group: Group) => {
  editingGroupId.value = group.id;
  groupFormName.value = group.name;
  groupFormDescription.value = group.description || "";
  groupFormColor.value = group.color || "#3B82F6";
  groupFormPriority.value = group.priority || 0;
  showEditGroupForm.value = true;
};

const selectGroup = async (group: Group) => {
  selectedGroup.value = null;
  await loadGroupDetails(group.id);
};

// Watch for when the user groups form opens to ensure data is loaded
watch(showUserGroupsForm, async (isOpen) => {
  if (isOpen && userId.value) {
    try {
      const groupIds = await getUserGroups(userId.value);
      userSelectedGroups.value = groupIds;
    } catch (err) {
      toast.error(getApiErrorMessage(err, "Failed to load user groups"));
    }
  }
});

onMounted(async () => {
  await Promise.all([loadUsers(), loadResources(), loadGroups()]);
});
</script>

<template>
  <div class="w-full h-full overflow-auto p-4 md:p-8 min-h-screen">
    <div class="container mx-auto max-w-6xl">
      <div class="mb-6 text-center md:text-left">
        <h1
          class="text-3xl font-bold bg-gradient-to-r from-primary to-primary/60 bg-clip-text text-transparent"
        >
          Permissions Management
        </h1>
        <p class="text-muted-foreground mt-2">
          Manage user permissions and groups for server creation.
        </p>
      </div>

      <Alert class="mb-6 border-2 bg-card/50 backdrop-blur-sm">
        <Info class="h-4 w-4" />
        <AlertDescription>
          <strong>User Permissions</strong> – Assign direct permissions to users (locations, nodes, realms, spells).
          <strong>Groups</strong> – Create groups and assign permissions to them; users in a group inherit those permissions.
          Use both for flexible access control.
        </AlertDescription>
      </Alert>

      <Tabs v-model="activeTab" class="w-full">
        <TabsList class="mb-6 grid w-full grid-cols-2 bg-muted/30 border border-border/50">
          <TabsTrigger value="users">
            <Users class="mr-2 h-4 w-4" />
            User Permissions
          </TabsTrigger>
          <TabsTrigger value="groups">
            <Users class="mr-2 h-4 w-4" />
            Groups
          </TabsTrigger>
        </TabsList>

        <!-- User Permissions Tab -->
        <TabsContent value="users" class="space-y-6">
          <!-- User Selection -->
          <Card class="p-6 mb-6 border-2 shadow-xl bg-card/50 backdrop-blur-sm">
            <div class="mb-4">
              <Label class="text-base font-semibold">Select User</Label>
              <p class="text-sm text-muted-foreground mt-1">
                Choose a user to manage their server creation permissions
              </p>
            </div>
            <Popover v-model:open="userSearchOpen">
              <PopoverTrigger as-child>
                <Button
                  variant="outline"
                  role="combobox"
                  :aria-expanded="userSearchOpen"
                  class="w-full justify-between"
                >
                  {{
                    selectedUser
                      ? `${selectedUser.username} (${selectedUser.email})`
                      : "Select user..."
                  }}
                  <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                </Button>
              </PopoverTrigger>
              <PopoverContent class="w-[400px] p-0">
                <Command>
                  <CommandInput
                    v-model="userSearch"
                    placeholder="Search users..."
                  />
                  <CommandEmpty>No user found.</CommandEmpty>
                  <CommandGroup>
                    <CommandItem
                      v-for="user in filteredUsers"
                      :key="user.id"
                      :value="user.id.toString()"
                      @select="selectUser(user)"
                    >
                      <Check
                        :class="
                          cn(
                            'mr-2 h-4 w-4',
                            selectedUser?.id === user.id
                              ? 'opacity-100'
                              : 'opacity-0'
                          )
                        "
                      />
                      <div>
                        <div class="font-medium">{{ user.username }}</div>
                        <div class="text-xs text-muted-foreground">
                          {{ user.email }}
                        </div>
                      </div>
                    </CommandItem>
                  </CommandGroup>
                </Command>
              </PopoverContent>
            </Popover>
          </Card>

          <!-- Permissions Display -->
          <div v-if="selectedUser && permissions">
            <Card class="p-6 mb-6 border-2 shadow-xl bg-card/50 backdrop-blur-sm">
              <div class="flex items-center justify-between mb-6">
                <div>
                  <h2 class="text-lg font-semibold">
                    Permissions for {{ permissions.user.username }}
                  </h2>
                  <p class="text-sm text-muted-foreground">
                    {{ permissions.user.email }}
                  </p>
                </div>
                <div class="flex gap-2">
                  <Button @click="showAddForm = !showAddForm">
                    <Plus class="mr-2 h-4 w-4" />
                    Add Permission
                  </Button>
                  <Button
                    variant="outline"
                    @click="showUserGroupsForm = !showUserGroupsForm"
                  >
                    <Users class="mr-2 h-4 w-4" />
                    Manage Groups
                  </Button>
                </div>
              </div>

              <!-- Add Permission Form -->
              <div v-if="showAddForm" class="border-t pt-6 mt-6">
                <div class="space-y-4">
                  <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-semibold">Add New Permission</h3>
                  </div>

                  <div>
                    <Label>Resource Type</Label>
                    <select
                      v-model="addFormResourceType"
                      class="mt-2 flex h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                    >
                      <option value="location">Location</option>
                      <option value="node">Node</option>
                      <option value="realm">Realm</option>
                      <option value="spell">Spell</option>
                    </select>
                  </div>

                  <div>
                    <Label>Resource</Label>
                    <Popover v-model:open="addFormOpen">
                      <PopoverTrigger as-child>
                        <Button
                          variant="outline"
                          role="combobox"
                          :aria-expanded="addFormOpen"
                          class="w-full justify-between mt-2"
                          :disabled="filteredResources.length === 0"
                        >
                          {{
                            addFormResourceId
                              ? getResourceName(
                                  addFormResourceType,
                                  addFormResourceId
                                )
                              : filteredResources.length === 0
                              ? `No ${addFormResourceType}s available`
                              : "Select resource..."
                          }}
                          <ChevronsUpDown
                            class="ml-2 h-4 w-4 shrink-0 opacity-50"
                          />
                        </Button>
                      </PopoverTrigger>
                      <PopoverContent class="w-[400px] p-0">
                        <Command>
                          <CommandInput placeholder="Search resources..." />
                          <CommandEmpty>No resource found.</CommandEmpty>
                          <CommandGroup>
                            <CommandItem
                              v-for="resource in filteredResources"
                              :key="resource.id"
                              :value="`${resource.name} ${
                                resource.description || ''
                              }`"
                              @select="
                                () => {
                                  addFormResourceId = resource.id;
                                  addFormOpen = false;
                                }
                              "
                            >
                              <Check
                                :class="
                                  cn(
                                    'mr-2 h-4 w-4',
                                    addFormResourceId === resource.id
                                      ? 'opacity-100'
                                      : 'opacity-0'
                                  )
                                "
                              />
                              <div>
                                <div class="font-medium">
                                  {{ resource.name }}
                                </div>
                                <div
                                  v-if="
                                    resource.description &&
                                    resource.description !== resource.name
                                  "
                                  class="text-xs text-muted-foreground"
                                >
                                  {{ resource.description }}
                                </div>
                              </div>
                            </CommandItem>
                          </CommandGroup>
                        </Command>
                      </PopoverContent>
                    </Popover>
                    <p
                      v-if="filteredResources.length === 0"
                      class="text-xs text-muted-foreground mt-1"
                    >
                      No {{ addFormResourceType }}s available. Please load
                      resources first.
                    </p>
                  </div>

                  <div>
                    <Label for="custom_error"
                      >Custom Error Message (Optional)</Label
                    >
                    <Input
                      id="custom_error"
                      v-model="addFormCustomError"
                      placeholder="Custom error message to show when user tries to use this resource"
                      class="mt-2"
                    />
                    <p class="text-xs text-muted-foreground mt-1">
                      Leave empty to use default error message
                    </p>
                  </div>

                  <div class="flex gap-2">
                    <Button @click="handleAddPermission" class="flex-1">
                      Add Permission
                    </Button>
                    <Button variant="outline" @click="showAddForm = false">
                      Cancel
                    </Button>
                  </div>
                </div>
              </div>
            </Card>

            <!-- User Groups Form -->
            <Card v-if="showUserGroupsForm" class="p-6 mb-6 border-2 shadow-xl bg-card/50 backdrop-blur-sm">
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-semibold">Manage User Groups</h3>
                <Button
                  variant="ghost"
                  size="sm"
                  @click="showUserGroupsForm = false"
                >
                  <XIcon class="h-4 w-4" />
                </Button>
              </div>
              <p class="text-sm text-muted-foreground mb-4">
                Select groups to assign to this user. Users inherit permissions
                from their groups.
              </p>
              <div
                v-if="groupsLoading"
                class="flex items-center justify-center py-8"
              >
                <Loader2 class="h-6 w-6 animate-spin" />
              </div>
              <div
                v-else-if="groups.length === 0"
                class="text-sm text-muted-foreground py-4"
              >
                No groups available. Create groups first.
              </div>
              <div v-else class="space-y-2">
                <div
                  v-for="group in groups"
                  :key="group.id"
                  @click="toggleUserGroup(group.id)"
                  class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-accent/50 transition-colors"
                  :class="{
                    'border-primary bg-primary/10': isUserGroupSelected(
                      group.id
                    ),
                  }"
                >
                  <div
                    class="flex h-5 w-5 items-center justify-center rounded border"
                    :class="{
                      'bg-primary border-primary': isUserGroupSelected(
                        group.id
                      ),
                    }"
                  >
                    <Check
                      v-if="isUserGroupSelected(group.id)"
                      class="h-4 w-4 text-primary-foreground"
                    />
                  </div>
                  <div
                    class="w-4 h-4 rounded-full"
                    :style="{ backgroundColor: group.color || '#3B82F6' }"
                  />
                  <div class="flex-1">
                    <div class="font-medium">{{ group.name }}</div>
                    <div
                      v-if="group.description"
                      class="text-xs text-muted-foreground"
                    >
                      {{ group.description }}
                    </div>
                  </div>
                </div>
              </div>
              <div class="flex gap-2 mt-4">
                <Button @click="handleSetUserGroups" class="flex-1">
                  Save Groups
                </Button>
                <Button variant="outline" @click="showUserGroupsForm = false">
                  Cancel
                </Button>
              </div>
            </Card>

            <!-- Permissions List -->
            <div class="space-y-6">
              <!-- Locations -->
              <Card class="p-6 border-2 shadow-xl bg-card/50 backdrop-blur-sm">
                <div class="flex items-center gap-2 mb-4">
                  <MapPin class="h-5 w-5" />
                  <h3 class="text-lg font-semibold">Locations</h3>
                </div>
                <div
                  v-if="permissions.permissions.locations.length === 0"
                  class="text-sm text-muted-foreground py-4"
                >
                  No location permissions
                </div>
                <div v-else class="space-y-2">
                  <div
                    v-for="perm in permissions.permissions.locations"
                    :key="perm.id"
                    class="flex items-center justify-between p-3 border rounded-lg hover:bg-accent/50 transition-colors"
                  >
                    <div class="flex-1">
                      <div class="font-medium">
                        {{ getResourceName("location", perm.resource_id) }}
                      </div>
                      <div
                        v-if="perm.custom_error_message"
                        class="text-xs text-muted-foreground mt-1"
                      >
                        Custom error: {{ perm.custom_error_message }}
                      </div>
                    </div>
                    <Button
                      variant="destructive"
                      size="sm"
                      @click="handleDeletePermission(perm.id)"
                    >
                      <Trash2 class="h-4 w-4" />
                    </Button>
                  </div>
                </div>
              </Card>

              <!-- Nodes -->
              <Card class="p-6 border-2 shadow-xl bg-card/50 backdrop-blur-sm">
                <div class="flex items-center gap-2 mb-4">
                  <Network class="h-5 w-5" />
                  <h3 class="text-lg font-semibold">Nodes</h3>
                </div>
                <div
                  v-if="permissions.permissions.nodes.length === 0"
                  class="text-sm text-muted-foreground py-4"
                >
                  No node permissions
                </div>
                <div v-else class="space-y-2">
                  <div
                    v-for="perm in permissions.permissions.nodes"
                    :key="perm.id"
                    class="flex items-center justify-between p-3 border rounded-lg hover:bg-accent/50 transition-colors"
                  >
                    <div class="flex-1">
                      <div class="font-medium">
                        {{ getResourceName("node", perm.resource_id) }}
                      </div>
                      <div
                        v-if="perm.custom_error_message"
                        class="text-xs text-muted-foreground mt-1"
                      >
                        Custom error: {{ perm.custom_error_message }}
                      </div>
                    </div>
                    <Button
                      variant="destructive"
                      size="sm"
                      @click="handleDeletePermission(perm.id)"
                    >
                      <Trash2 class="h-4 w-4" />
                    </Button>
                  </div>
                </div>
              </Card>

              <!-- Realms -->
              <Card class="p-6 border-2 shadow-xl bg-card/50 backdrop-blur-sm">
                <div class="flex items-center gap-2 mb-4">
                  <Box class="h-5 w-5" />
                  <h3 class="text-lg font-semibold">Realms</h3>
                </div>
                <div
                  v-if="permissions.permissions.realms.length === 0"
                  class="text-sm text-muted-foreground py-4"
                >
                  No realm permissions
                </div>
                <div v-else class="space-y-2">
                  <div
                    v-for="perm in permissions.permissions.realms"
                    :key="perm.id"
                    class="flex items-center justify-between p-3 border rounded-lg hover:bg-accent/50 transition-colors"
                  >
                    <div class="flex-1">
                      <div class="font-medium">
                        {{ getResourceName("realm", perm.resource_id) }}
                      </div>
                      <div
                        v-if="perm.custom_error_message"
                        class="text-xs text-muted-foreground mt-1"
                      >
                        Custom error: {{ perm.custom_error_message }}
                      </div>
                    </div>
                    <Button
                      variant="destructive"
                      size="sm"
                      @click="handleDeletePermission(perm.id)"
                    >
                      <Trash2 class="h-4 w-4" />
                    </Button>
                  </div>
                </div>
              </Card>

              <!-- Spells -->
              <Card class="p-6 border-2 shadow-xl bg-card/50 backdrop-blur-sm">
                <div class="flex items-center gap-2 mb-4">
                  <Sparkles class="h-5 w-5" />
                  <h3 class="text-lg font-semibold">Spells</h3>
                </div>
                <div
                  v-if="permissions.permissions.spells.length === 0"
                  class="text-sm text-muted-foreground py-4"
                >
                  No spell permissions
                </div>
                <div v-else class="space-y-2">
                  <div
                    v-for="perm in permissions.permissions.spells"
                    :key="perm.id"
                    class="flex items-center justify-between p-3 border rounded-lg hover:bg-accent/50 transition-colors"
                  >
                    <div class="flex-1">
                      <div class="font-medium">
                        {{ getResourceName("spell", perm.resource_id) }}
                      </div>
                      <div
                        v-if="perm.custom_error_message"
                        class="text-xs text-muted-foreground mt-1"
                      >
                        Custom error: {{ perm.custom_error_message }}
                      </div>
                    </div>
                    <Button
                      variant="destructive"
                      size="sm"
                      @click="handleDeletePermission(perm.id)"
                    >
                      <Trash2 class="h-4 w-4" />
                    </Button>
                  </div>
                </div>
              </Card>
            </div>
          </div>

          <!-- Loading State -->
          <div
            v-if="loading && !permissions"
            class="flex items-center justify-center py-12"
          >
            <Loader2 class="h-8 w-8 animate-spin" />
          </div>

          <!-- Error State -->
          <Alert v-if="error && !permissions" variant="destructive" class="border-2">
            <AlertCircle class="h-4 w-4" />
            <AlertDescription class="font-medium">{{ error }}</AlertDescription>
          </Alert>
        </TabsContent>

        <!-- Groups Tab -->
        <TabsContent value="groups" class="space-y-6">
          <!-- Groups List -->
          <Card class="p-6 border-2 shadow-xl bg-card/50 backdrop-blur-sm">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h2 class="text-lg font-semibold">Groups</h2>
                <p class="text-sm text-muted-foreground">
                  Create and manage groups to assign permissions to multiple
                  users at once
                </p>
              </div>
              <Button @click="showCreateGroupForm = !showCreateGroupForm">
                <Plus class="mr-2 h-4 w-4" />
                Create Group
              </Button>
            </div>

            <!-- Create Group Form -->
            <div v-if="showCreateGroupForm" class="border-t pt-6 mt-6">
              <div class="space-y-4">
                <div class="flex items-center justify-between mb-4">
                  <h3 class="text-base font-semibold">Create New Group</h3>
                  <Button variant="ghost" size="sm" @click="resetGroupForm">
                    <XIcon class="h-4 w-4" />
                  </Button>
                </div>

                <div>
                  <Label for="group_name">Group Name *</Label>
                  <Input
                    id="group_name"
                    v-model="groupFormName"
                    placeholder="e.g., Premium Users"
                    class="mt-2"
                  />
                </div>

                <div>
                  <Label for="group_description">Description</Label>
                  <Input
                    id="group_description"
                    v-model="groupFormDescription"
                    placeholder="Optional description"
                    class="mt-2"
                  />
                </div>

                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <Label for="group_color">Color</Label>
                    <Input
                      id="group_color"
                      v-model="groupFormColor"
                      type="color"
                      class="mt-2 h-10"
                    />
                  </div>
                  <div>
                    <Label for="group_priority">Priority</Label>
                    <Input
                      id="group_priority"
                      v-model.number="groupFormPriority"
                      type="number"
                      placeholder="0"
                      class="mt-2"
                    />
                  </div>
                </div>

                <div class="flex gap-2">
                  <Button @click="handleCreateGroup" class="flex-1">
                    Create Group
                  </Button>
                  <Button variant="outline" @click="resetGroupForm">
                    Cancel
                  </Button>
                </div>
              </div>
            </div>

            <!-- Edit Group Form -->
            <div v-if="showEditGroupForm" class="border-t pt-6 mt-6">
              <div class="space-y-4">
                <div class="flex items-center justify-between mb-4">
                  <h3 class="text-base font-semibold">Edit Group</h3>
                  <Button variant="ghost" size="sm" @click="resetGroupForm">
                    <XIcon class="h-4 w-4" />
                  </Button>
                </div>

                <div>
                  <Label for="edit_group_name">Group Name *</Label>
                  <Input
                    id="edit_group_name"
                    v-model="groupFormName"
                    placeholder="e.g., Premium Users"
                    class="mt-2"
                  />
                </div>

                <div>
                  <Label for="edit_group_description">Description</Label>
                  <Input
                    id="edit_group_description"
                    v-model="groupFormDescription"
                    placeholder="Optional description"
                    class="mt-2"
                  />
                </div>

                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <Label for="edit_group_color">Color</Label>
                    <Input
                      id="edit_group_color"
                      v-model="groupFormColor"
                      type="color"
                      class="mt-2 h-10"
                    />
                  </div>
                  <div>
                    <Label for="edit_group_priority">Priority</Label>
                    <Input
                      id="edit_group_priority"
                      v-model.number="groupFormPriority"
                      type="number"
                      placeholder="0"
                      class="mt-2"
                    />
                  </div>
                </div>

                <div class="flex gap-2">
                  <Button @click="handleUpdateGroup" class="flex-1">
                    Update Group
                  </Button>
                  <Button variant="outline" @click="resetGroupForm">
                    Cancel
                  </Button>
                </div>
              </div>
            </div>

            <!-- Groups List -->
            <div
              v-if="groupsLoading"
              class="flex items-center justify-center py-8"
            >
              <Loader2 class="h-6 w-6 animate-spin" />
            </div>
            <div
              v-else-if="!groups || groups.length === 0"
              class="text-center py-8 text-muted-foreground"
            >
              <p>No groups found. Create your first group to get started.</p>
              <p class="text-xs mt-2">
                Groups let you assign permissions to multiple users at once.
              </p>
            </div>
            <div v-else class="space-y-2 mt-6">
              <div
                v-for="group in groups"
                :key="group.id"
                class="flex items-center justify-between p-4 border rounded-lg hover:bg-accent/50 transition-colors cursor-pointer"
                :class="{
                  'border-primary bg-primary/10':
                    selectedGroup?.id === group.id,
                }"
                @click="selectGroup(group)"
              >
                <div class="flex items-center gap-3 flex-1">
                  <div
                    class="w-4 h-4 rounded-full"
                    :style="{ backgroundColor: group.color || '#3B82F6' }"
                  />
                  <div class="flex-1">
                    <div class="font-medium">{{ group.name }}</div>
                    <div
                      v-if="group.description"
                      class="text-xs text-muted-foreground"
                    >
                      {{ group.description }}
                    </div>
                    <div class="text-xs text-muted-foreground">
                      Priority: {{ group.priority }}
                    </div>
                  </div>
                </div>
                <div class="flex gap-2" @click.stop>
                  <Button
                    variant="ghost"
                    size="sm"
                    @click="openEditGroupForm(group)"
                  >
                    <Edit class="h-4 w-4" />
                  </Button>
                  <Button
                    variant="destructive"
                    size="sm"
                    @click="handleDeleteGroup(group.id)"
                  >
                    <Trash2 class="h-4 w-4" />
                  </Button>
                </div>
              </div>
            </div>
          </Card>

          <!-- Selected Group Details -->
          <div v-if="selectedGroup" class="space-y-6">
            <!-- Group Info -->
            <Card class="p-6 border-2 shadow-xl bg-card/50 backdrop-blur-sm">
              <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                  <div
                    class="w-6 h-6 rounded-full"
                    :style="{
                      backgroundColor: selectedGroup.color || '#3B82F6',
                    }"
                  />
                  <div>
                    <h3 class="text-lg font-semibold">
                      {{ selectedGroup.name }}
                    </h3>
                    <p
                      v-if="selectedGroup.description"
                      class="text-sm text-muted-foreground"
                    >
                      {{ selectedGroup.description }}
                    </p>
                  </div>
                </div>
                <div class="flex gap-2">
                  <Button
                    variant="outline"
                    @click="
                      showGroupPermissionsForm = !showGroupPermissionsForm
                    "
                  >
                    <Plus class="mr-2 h-4 w-4" />
                    Add Permission
                  </Button>
                </div>
              </div>

              <!-- Add Permission Dialog -->
              <Dialog
                :open="showGroupPermissionsForm"
                @update:open="(val) => (showGroupPermissionsForm = val)"
              >
                <DialogContent class="sm:max-w-[500px]">
                  <DialogHeader>
                    <DialogTitle>Add Permission</DialogTitle>
                    <DialogDescription>
                      Add a new permission to this group. Users in this group
                      will inherit this permission.
                    </DialogDescription>
                  </DialogHeader>

                  <div class="space-y-4 py-4">
                    <div class="space-y-2">
                      <Label for="resource-type">Resource Type</Label>
                      <select
                        id="resource-type"
                        v-model="groupPermissionResourceType"
                        class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                      >
                        <option value="location">Location</option>
                        <option value="node">Node</option>
                        <option value="realm">Realm</option>
                        <option value="spell">Spell</option>
                      </select>
                    </div>

                    <div class="space-y-2">
                      <Label>Resource</Label>
                      <Popover v-model:open="groupPermissionOpen">
                        <PopoverTrigger as-child>
                          <Button
                            variant="outline"
                            role="combobox"
                            :aria-expanded="groupPermissionOpen"
                            class="w-full justify-between"
                            :disabled="filteredGroupResources.length === 0"
                          >
                            {{
                              groupPermissionResourceId
                                ? getResourceName(
                                    groupPermissionResourceType,
                                    groupPermissionResourceId
                                  )
                                : filteredGroupResources.length === 0
                                ? `No ${groupPermissionResourceType}s available`
                                : "Select resource..."
                            }}
                            <ChevronsUpDown
                              class="ml-2 h-4 w-4 shrink-0 opacity-50"
                            />
                          </Button>
                        </PopoverTrigger>
                        <PopoverContent class="w-[400px] p-0">
                          <Command>
                            <CommandInput placeholder="Search resources..." />
                            <CommandEmpty>No resource found.</CommandEmpty>
                            <CommandGroup>
                              <CommandItem
                                v-for="resource in filteredGroupResources"
                                :key="resource.id"
                                :value="`${resource.name} ${
                                  resource.description || ''
                                }`"
                                @select="
                                  () => {
                                    groupPermissionResourceId = resource.id;
                                    groupPermissionOpen = false;
                                  }
                                "
                              >
                                <Check
                                  :class="
                                    cn(
                                      'mr-2 h-4 w-4',
                                      groupPermissionResourceId === resource.id
                                        ? 'opacity-100'
                                        : 'opacity-0'
                                    )
                                  "
                                />
                                <div>
                                  <div class="font-medium">
                                    {{ resource.name }}
                                  </div>
                                  <div
                                    v-if="
                                      resource.description &&
                                      resource.description !== resource.name
                                    "
                                    class="text-xs text-muted-foreground"
                                  >
                                    {{ resource.description }}
                                  </div>
                                </div>
                              </CommandItem>
                            </CommandGroup>
                          </Command>
                        </PopoverContent>
                      </Popover>
                      <p
                        v-if="filteredGroupResources.length === 0"
                        class="text-xs text-muted-foreground"
                      >
                        No {{ groupPermissionResourceType }}s available. Please
                        load resources first.
                      </p>
                    </div>

                    <div class="space-y-2">
                      <Label for="group_custom_error"
                        >Custom Error Message (Optional)</Label
                      >
                      <Input
                        id="group_custom_error"
                        v-model="groupPermissionCustomError"
                        placeholder="Custom error message to show when user tries to use this resource"
                      />
                      <p class="text-xs text-muted-foreground">
                        Leave empty to use default error message
                      </p>
                    </div>
                  </div>

                  <div class="flex justify-end gap-2">
                    <Button variant="outline" @click="resetGroupPermissionForm">
                      Cancel
                    </Button>
                    <Button @click="handleAddGroupPermission">
                      Add Permission
                    </Button>
                  </div>
                </DialogContent>
              </Dialog>
            </Card>

            <!-- Group Permissions -->
            <div class="space-y-6">
              <!-- Locations -->
              <Card class="p-6 border-2 shadow-xl bg-card/50 backdrop-blur-sm">
                <div class="flex items-center gap-2 mb-4">
                  <MapPin class="h-5 w-5" />
                  <h3 class="text-lg font-semibold">Location Permissions</h3>
                </div>
                <div
                  v-if="selectedGroup.permissions.locations.length === 0"
                  class="text-sm text-muted-foreground py-4"
                >
                  No location permissions
                </div>
                <div v-else class="space-y-2">
                  <div
                    v-for="perm in selectedGroup.permissions.locations"
                    :key="perm.id"
                    class="flex items-center justify-between p-3 border rounded-lg hover:bg-accent/50 transition-colors"
                  >
                    <div class="flex-1">
                      <div class="font-medium">
                        {{ getResourceName("location", perm.resource_id) }}
                      </div>
                      <div
                        v-if="perm.custom_error_message"
                        class="text-xs text-muted-foreground mt-1"
                      >
                        Custom error: {{ perm.custom_error_message }}
                      </div>
                      <!-- Edit form -->
                      <div
                        v-if="
                          editingPermission?.resourceType === 'location' &&
                          editingPermission?.resourceId === perm.resource_id
                        "
                        class="mt-3 p-3 bg-accent rounded-md space-y-2"
                      >
                        <Label>Custom Error Message</Label>
                        <Input
                          v-model="editPermissionCustomError"
                          placeholder="Custom error message"
                        />
                        <div class="flex gap-2">
                          <Button
                            size="sm"
                            @click="handleUpdateGroupPermission"
                          >
                            Save
                          </Button>
                          <Button
                            variant="outline"
                            size="sm"
                            @click="
                              editingPermission = null;
                              editPermissionCustomError = '';
                            "
                          >
                            Cancel
                          </Button>
                        </div>
                      </div>
                    </div>
                    <div class="flex gap-2">
                      <Button
                        variant="outline"
                        size="sm"
                        @click="
                          handleEditGroupPermission(
                            'location',
                            perm.resource_id,
                            perm.custom_error_message || undefined
                          )
                        "
                      >
                        <Edit class="h-4 w-4" />
                      </Button>
                      <Button
                        variant="destructive"
                        size="sm"
                        @click="
                          handleDeleteGroupPermission(
                            'location',
                            perm.resource_id
                          )
                        "
                      >
                        <Trash2 class="h-4 w-4" />
                      </Button>
                    </div>
                  </div>
                </div>
              </Card>

              <!-- Nodes -->
              <Card class="p-6 border-2 shadow-xl bg-card/50 backdrop-blur-sm">
                <div class="flex items-center gap-2 mb-4">
                  <Network class="h-5 w-5" />
                  <h3 class="text-lg font-semibold">Node Permissions</h3>
                </div>
                <div
                  v-if="selectedGroup.permissions.nodes.length === 0"
                  class="text-sm text-muted-foreground py-4"
                >
                  No node permissions
                </div>
                <div v-else class="space-y-2">
                  <div
                    v-for="perm in selectedGroup.permissions.nodes"
                    :key="perm.id"
                    class="flex items-center justify-between p-3 border rounded-lg hover:bg-accent/50 transition-colors"
                  >
                    <div class="flex-1">
                      <div class="font-medium">
                        {{ getResourceName("node", perm.resource_id) }}
                      </div>
                      <div
                        v-if="perm.custom_error_message"
                        class="text-xs text-muted-foreground mt-1"
                      >
                        Custom error: {{ perm.custom_error_message }}
                      </div>
                      <!-- Edit form -->
                      <div
                        v-if="
                          editingPermission?.resourceType === 'node' &&
                          editingPermission?.resourceId === perm.resource_id
                        "
                        class="mt-3 p-3 bg-accent rounded-md space-y-2"
                      >
                        <Label>Custom Error Message</Label>
                        <Input
                          v-model="editPermissionCustomError"
                          placeholder="Custom error message"
                        />
                        <div class="flex gap-2">
                          <Button
                            size="sm"
                            @click="handleUpdateGroupPermission"
                          >
                            Save
                          </Button>
                          <Button
                            variant="outline"
                            size="sm"
                            @click="
                              editingPermission = null;
                              editPermissionCustomError = '';
                            "
                          >
                            Cancel
                          </Button>
                        </div>
                      </div>
                    </div>
                    <div class="flex gap-2">
                      <Button
                        variant="outline"
                        size="sm"
                        @click="
                          handleEditGroupPermission(
                            'node',
                            perm.resource_id,
                            perm.custom_error_message || undefined
                          )
                        "
                      >
                        <Edit class="h-4 w-4" />
                      </Button>
                      <Button
                        variant="destructive"
                        size="sm"
                        @click="
                          handleDeleteGroupPermission('node', perm.resource_id)
                        "
                      >
                        <Trash2 class="h-4 w-4" />
                      </Button>
                    </div>
                  </div>
                </div>
              </Card>

              <!-- Realms -->
              <Card class="p-6 border-2 shadow-xl bg-card/50 backdrop-blur-sm">
                <div class="flex items-center gap-2 mb-4">
                  <Box class="h-5 w-5" />
                  <h3 class="text-lg font-semibold">Realm Permissions</h3>
                </div>
                <div
                  v-if="selectedGroup.permissions.realms.length === 0"
                  class="text-sm text-muted-foreground py-4"
                >
                  No realm permissions
                </div>
                <div v-else class="space-y-2">
                  <div
                    v-for="perm in selectedGroup.permissions.realms"
                    :key="perm.id"
                    class="flex items-center justify-between p-3 border rounded-lg hover:bg-accent/50 transition-colors"
                  >
                    <div class="flex-1">
                      <div class="font-medium">
                        {{ getResourceName("realm", perm.resource_id) }}
                      </div>
                      <div
                        v-if="perm.custom_error_message"
                        class="text-xs text-muted-foreground mt-1"
                      >
                        Custom error: {{ perm.custom_error_message }}
                      </div>
                      <!-- Edit form -->
                      <div
                        v-if="
                          editingPermission?.resourceType === 'realm' &&
                          editingPermission?.resourceId === perm.resource_id
                        "
                        class="mt-3 p-3 bg-accent rounded-md space-y-2"
                      >
                        <Label>Custom Error Message</Label>
                        <Input
                          v-model="editPermissionCustomError"
                          placeholder="Custom error message"
                        />
                        <div class="flex gap-2">
                          <Button
                            size="sm"
                            @click="handleUpdateGroupPermission"
                          >
                            Save
                          </Button>
                          <Button
                            variant="outline"
                            size="sm"
                            @click="
                              editingPermission = null;
                              editPermissionCustomError = '';
                            "
                          >
                            Cancel
                          </Button>
                        </div>
                      </div>
                    </div>
                    <div class="flex gap-2">
                      <Button
                        variant="outline"
                        size="sm"
                        @click="
                          handleEditGroupPermission(
                            'realm',
                            perm.resource_id,
                            perm.custom_error_message || undefined
                          )
                        "
                      >
                        <Edit class="h-4 w-4" />
                      </Button>
                      <Button
                        variant="destructive"
                        size="sm"
                        @click="
                          handleDeleteGroupPermission('realm', perm.resource_id)
                        "
                      >
                        <Trash2 class="h-4 w-4" />
                      </Button>
                    </div>
                  </div>
                </div>
              </Card>

              <!-- Spells -->
              <Card class="p-6 border-2 shadow-xl bg-card/50 backdrop-blur-sm">
                <div class="flex items-center gap-2 mb-4">
                  <Sparkles class="h-5 w-5" />
                  <h3 class="text-lg font-semibold">Spell Permissions</h3>
                </div>
                <div
                  v-if="selectedGroup.permissions.spells.length === 0"
                  class="text-sm text-muted-foreground py-4"
                >
                  No spell permissions
                </div>
                <div v-else class="space-y-2">
                  <div
                    v-for="perm in selectedGroup.permissions.spells"
                    :key="perm.id"
                    class="flex items-center justify-between p-3 border rounded-lg hover:bg-accent/50 transition-colors"
                  >
                    <div class="flex-1">
                      <div class="font-medium">
                        {{ getResourceName("spell", perm.resource_id) }}
                      </div>
                      <div
                        v-if="perm.custom_error_message"
                        class="text-xs text-muted-foreground mt-1"
                      >
                        Custom error: {{ perm.custom_error_message }}
                      </div>
                    </div>
                    <Button
                      variant="destructive"
                      size="sm"
                      @click="
                        handleDeleteGroupPermission('spell', perm.resource_id)
                      "
                    >
                      <Trash2 class="h-4 w-4" />
                    </Button>
                  </div>
                </div>
              </Card>
            </div>
          </div>
        </TabsContent>
      </Tabs>
    </div>
  </div>
</template>
