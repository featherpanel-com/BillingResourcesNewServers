import { ref } from "vue";
import axios from "axios";
import type { AxiosError } from "axios";

export interface ApiResponse<T> {
  success: boolean;
  message?: string;
  data?: T;
  error?: boolean;
  error_message?: string;
  error_code?: string;
}

export interface UserPermission {
  id: number;
  resource_type: "location" | "node" | "realm" | "spell";
  resource_id: number;
  custom_error_message?: string;
}

export interface UserPermissionsData {
  user: {
    id: number;
    username: string;
    email: string;
  };
  permissions: {
    locations: Array<{
      id: number;
      resource_id: number;
      custom_error_message?: string;
    }>;
    nodes: Array<{
      id: number;
      resource_id: number;
      custom_error_message?: string;
    }>;
    realms: Array<{
      id: number;
      resource_id: number;
      custom_error_message?: string;
    }>;
    spells: Array<{
      id: number;
      resource_id: number;
      custom_error_message?: string;
    }>;
  };
}

export interface AddPermissionData {
  resource_type: "location" | "node" | "realm" | "spell";
  resource_id: number;
  custom_error_message?: string;
}

export function useUserPermissionsAPI() {
  const loading = ref(false);
  const error = ref<string | null>(null);

  const handleError = (err: unknown): string => {
    if (axios.isAxiosError(err)) {
      const axiosError = err as AxiosError<{
        error_message?: string;
        message?: string;
      }>;
      return (
        axiosError.response?.data?.error_message ||
        axiosError.response?.data?.message ||
        axiosError.message ||
        "An error occurred"
      );
    }
    return err instanceof Error ? err.message : "An unknown error occurred";
  };

  const getUserPermissions = async (
    userId: number
  ): Promise<UserPermissionsData> => {
    loading.value = true;
    error.value = null;
    try {
      const response = await axios.get<ApiResponse<UserPermissionsData>>(
        `/api/admin/billingresourcesnewservers/user-permissions/${userId}`
      );

      if (response.data && response.data.success && response.data.data) {
        return response.data.data;
      }

      throw new Error(
        response.data?.error_message ||
          response.data?.message ||
          "Invalid response format"
      );
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  const addPermission = async (
    userId: number,
    data: AddPermissionData
  ): Promise<void> => {
    loading.value = true;
    error.value = null;
    try {
      const response = await axios.post<ApiResponse<void>>(
        `/api/admin/billingresourcesnewservers/user-permissions/${userId}`,
        data
      );

      if (!response.data || !response.data.success) {
        throw new Error(
          response.data?.error_message ||
            response.data?.message ||
            "Failed to add permission"
        );
      }
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  const deletePermission = async (
    userId: number,
    permissionId: number
  ): Promise<void> => {
    loading.value = true;
    error.value = null;
    try {
      const response = await axios.delete<ApiResponse<void>>(
        `/api/admin/billingresourcesnewservers/user-permissions/${userId}/${permissionId}`
      );

      if (!response.data || !response.data.success) {
        throw new Error(
          response.data?.error_message ||
            response.data?.message ||
            "Failed to delete permission"
        );
      }
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  return {
    loading,
    error,
    getUserPermissions,
    addPermission,
    deletePermission,
  };
}

