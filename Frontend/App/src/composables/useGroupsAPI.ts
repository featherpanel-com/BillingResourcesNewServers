import { ref } from "vue";
import axios from "axios";

function extractApiError(err: unknown, fallback: string): string {
  if (axios.isAxiosError(err)) {
    const data = err.response?.data as
      | { error_message?: string; message?: string }
      | undefined;
    return data?.error_message || data?.message || err.message || fallback;
  }
  return err instanceof Error ? err.message : fallback;
}

export interface Group {
  id: number;
  name: string;
  description: string | null;
  color: string | null;
  priority: number;
  created_at: string;
  updated_at: string;
}

export interface GroupPermission {
  id: number;
  group_id: number;
  resource_type: "location" | "node" | "realm" | "spell";
  resource_id: number;
  custom_error_message: string | null;
  created_at: string;
  updated_at: string;
}

export interface GroupWithPermissions extends Group {
  permissions: {
    locations: GroupPermission[];
    nodes: GroupPermission[];
    realms: GroupPermission[];
    spells: GroupPermission[];
  };
}

export interface GroupUser {
  id: number;
  user_id: number;
  group_id: number;
  created_at: string;
  user?: {
    id: number;
    username: string;
    email: string;
  };
}

export interface CreateGroupData {
  name: string;
  description?: string;
  color?: string;
  priority?: number;
}

export interface UpdateGroupData {
  name?: string;
  description?: string;
  color?: string;
  priority?: number;
}

export interface AddGroupPermissionData {
  resource_type: "location" | "node" | "realm" | "spell";
  resource_id: number;
  custom_error_message?: string;
}

export interface UpdateGroupPermissionData {
  custom_error_message?: string;
}

export function useGroupsAPI() {
  const loading = ref(false);
  const error = ref<string | null>(null);

  const getGroups = async (): Promise<Group[]> => {
    loading.value = true;
    error.value = null;
    try {
      const response = await axios.get(
        "/api/admin/billingresourcesnewservers/groups"
      );
      // The API returns { success: true, data: [...] } where data is the array of groups directly
      // axios wraps the response, so response.data is the parsed JSON body
      const apiResponse = response.data;
      if (apiResponse?.success && Array.isArray(apiResponse.data)) {
        return apiResponse.data;
      }
      // Fallback: check if data exists but isn't wrapped in success
      if (Array.isArray(apiResponse?.data)) {
        return apiResponse.data;
      }
      return [];
    } catch (err) {
      const msg = extractApiError(err, "Failed to fetch groups");
      error.value = msg;
      throw new Error(msg);
    } finally {
      loading.value = false;
    }
  };

  const getGroup = async (groupId: number): Promise<GroupWithPermissions> => {
    loading.value = true;
    error.value = null;
    try {
      const response = await axios.get(
        `/api/admin/billingresourcesnewservers/groups/${groupId}`
      );
      return response.data?.data;
    } catch (err) {
      const msg = extractApiError(err, "Failed to fetch group");
      error.value = msg;
      throw new Error(msg);
    } finally {
      loading.value = false;
    }
  };

  const createGroup = async (data: CreateGroupData): Promise<Group> => {
    loading.value = true;
    error.value = null;
    try {
      const response = await axios.post(
        "/api/admin/billingresourcesnewservers/groups",
        data
      );
      return response.data?.data;
    } catch (err) {
      const msg = extractApiError(err, "Failed to create group");
      error.value = msg;
      throw new Error(msg);
    } finally {
      loading.value = false;
    }
  };

  const updateGroup = async (
    groupId: number,
    data: UpdateGroupData
  ): Promise<Group> => {
    loading.value = true;
    error.value = null;
    try {
      const response = await axios.patch(
        `/api/admin/billingresourcesnewservers/groups/${groupId}`,
        data
      );
      return response.data?.data;
    } catch (err) {
      const msg = extractApiError(err, "Failed to update group");
      error.value = msg;
      throw new Error(msg);
    } finally {
      loading.value = false;
    }
  };

  const deleteGroup = async (groupId: number): Promise<void> => {
    loading.value = true;
    error.value = null;
    try {
      await axios.delete(
        `/api/admin/billingresourcesnewservers/groups/${groupId}`
      );
    } catch (err) {
      const msg = extractApiError(err, "Failed to delete group");
      error.value = msg;
      throw new Error(msg);
    } finally {
      loading.value = false;
    }
  };

  const addGroupPermission = async (
    groupId: number,
    data: AddGroupPermissionData
  ): Promise<GroupPermission> => {
    loading.value = true;
    error.value = null;
    try {
      const response = await axios.post(
        `/api/admin/billingresourcesnewservers/groups/${groupId}/permissions`,
        data
      );
      return response.data?.data;
    } catch (err) {
      const msg = extractApiError(err, "Failed to add group permission");
      error.value = msg;
      throw new Error(msg);
    } finally {
      loading.value = false;
    }
  };

  const updateGroupPermission = async (
    groupId: number,
    resourceType: "location" | "node" | "realm" | "spell",
    resourceId: number,
    data: UpdateGroupPermissionData
  ): Promise<GroupPermission> => {
    loading.value = true;
    error.value = null;
    try {
      const response = await axios.patch(
        `/api/admin/billingresourcesnewservers/groups/${groupId}/permissions/${resourceType}/${resourceId}`,
        data
      );
      return response.data?.data || null;
    } catch (err) {
      const msg = extractApiError(err, "Failed to update group permission");
      error.value = msg;
      throw new Error(msg);
    } finally {
      loading.value = false;
    }
  };

  const deleteGroupPermission = async (
    groupId: number,
    resourceType: "location" | "node" | "realm" | "spell",
    resourceId: number
  ): Promise<void> => {
    loading.value = true;
    error.value = null;
    try {
      await axios.delete(
        `/api/admin/billingresourcesnewservers/groups/${groupId}/permissions/${resourceType}/${resourceId}`
      );
    } catch (err) {
      const msg = extractApiError(err, "Failed to delete group permission");
      error.value = msg;
      throw new Error(msg);
    } finally {
      loading.value = false;
    }
  };

  const getGroupUsers = async (groupId: number): Promise<GroupUser[]> => {
    loading.value = true;
    error.value = null;
    try {
      const response = await axios.get(
        `/api/admin/billingresourcesnewservers/groups/${groupId}/users`
      );
      // The API returns { success: true, data: { group: {...}, users: [...] } }
      // where users array contains { id, uuid, username, email }
      const apiResponse = response.data;
      let users: Array<{
        id: number;
        uuid?: string;
        username?: string;
        email?: string;
      }> = [];

      if (apiResponse?.success && apiResponse?.data?.users) {
        users = Array.isArray(apiResponse.data.users)
          ? apiResponse.data.users
          : [];
      } else if (
        apiResponse?.data?.users &&
        Array.isArray(apiResponse.data.users)
      ) {
        users = apiResponse.data.users;
      }

      // Transform the API response to match GroupUser interface
      return users.map((user) => ({
        id: 0, // This is the UserGroup pivot table id, not available from this endpoint
        user_id: user.id, // The user's id
        group_id: groupId,
        created_at: "",
        user: {
          id: user.id,
          username: user.username || "",
          email: user.email || "",
        },
      }));
    } catch (err) {
      const msg = extractApiError(err, "Failed to fetch group users");
      error.value = msg;
      throw new Error(msg);
    } finally {
      loading.value = false;
    }
  };

  const assignUserToGroup = async (
    groupId: number,
    userId: number
  ): Promise<void> => {
    loading.value = true;
    error.value = null;
    try {
      await axios.post(
        `/api/admin/billingresourcesnewservers/groups/${groupId}/users/${userId}`
      );
    } catch (err) {
      const msg = extractApiError(err, "Failed to assign user to group");
      error.value = msg;
      throw new Error(msg);
    } finally {
      loading.value = false;
    }
  };

  const removeUserFromGroup = async (
    groupId: number,
    userId: number
  ): Promise<void> => {
    loading.value = true;
    error.value = null;
    try {
      await axios.delete(
        `/api/admin/billingresourcesnewservers/groups/${groupId}/users/${userId}`
      );
    } catch (err) {
      const msg = extractApiError(err, "Failed to remove user from group");
      error.value = msg;
      throw new Error(msg);
    } finally {
      loading.value = false;
    }
  };

  const getUserGroups = async (userId: number): Promise<number[]> => {
    loading.value = true;
    error.value = null;
    try {
      const response = await axios.get(
        `/api/admin/billingresourcesnewservers/users/${userId}/groups`
      );
      const data = response.data?.data;
      return Array.isArray(data?.group_ids) ? data.group_ids : [];
    } catch (err) {
      const msg = extractApiError(err, "Failed to fetch user groups");
      error.value = msg;
      throw new Error(msg);
    } finally {
      loading.value = false;
    }
  };

  const setUserGroups = async (
    userId: number,
    groupIds: number[]
  ): Promise<void> => {
    loading.value = true;
    error.value = null;
    try {
      await axios.post(
        `/api/admin/billingresourcesnewservers/users/${userId}/groups`,
        { group_ids: groupIds }
      );
    } catch (err) {
      const msg = extractApiError(err, "Failed to set user groups");
      error.value = msg;
      throw new Error(msg);
    } finally {
      loading.value = false;
    }
  };

  return {
    loading,
    error,
    getGroups,
    getGroup,
    getUserGroups,
    createGroup,
    updateGroup,
    deleteGroup,
    addGroupPermission,
    updateGroupPermission,
    deleteGroupPermission,
    getGroupUsers,
    assignUserToGroup,
    removeUserFromGroup,
    setUserGroups,
  };
}
