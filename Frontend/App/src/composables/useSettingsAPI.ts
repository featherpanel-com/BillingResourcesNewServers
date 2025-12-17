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

export interface PluginSettings {
  user_creation_enabled: boolean;
  user_restriction_mode?: 'all' | 'specific';
  allowed_users?: number[];
  allowed_locations: number[];
  allowed_nodes: number[];
  allowed_realms: number[];
  allowed_spells: number[];
  minimum_memory?: number;
  minimum_cpu?: number;
  minimum_disk?: number;
  permission_mode_location?: 'open' | 'restricted';
  permission_mode_node?: 'open' | 'restricted';
  permission_mode_realm?: 'open' | 'restricted';
  permission_mode_spell?: 'open' | 'restricted';
  default_error_location?: string;
  default_error_node?: string;
  default_error_realm?: string;
  default_error_spell?: string;
}

export interface UpdateSettingsData {
  user_creation_enabled?: boolean;
  user_restriction_mode?: 'all' | 'specific';
  allowed_users?: number[];
  allowed_locations?: number[];
  allowed_nodes?: number[];
  allowed_realms?: number[];
  allowed_spells?: number[];
  minimum_memory?: number;
  minimum_cpu?: number;
  minimum_disk?: number;
  permission_mode_location?: 'open' | 'restricted';
  permission_mode_node?: 'open' | 'restricted';
  permission_mode_realm?: 'open' | 'restricted';
  permission_mode_spell?: 'open' | 'restricted';
  default_error_location?: string;
  default_error_node?: string;
  default_error_realm?: string;
  default_error_spell?: string;
}

export function useSettingsAPI() {
  const loading = ref(false);
  const error = ref<string | null>(null);

  const handleError = (err: unknown): string => {
    if (axios.isAxiosError(err)) {
      const axiosError = err as AxiosError<{ error_message?: string; message?: string }>;
      return (
        axiosError.response?.data?.error_message ||
        axiosError.response?.data?.message ||
        axiosError.message ||
        "An error occurred"
      );
    }
    return err instanceof Error ? err.message : "An unknown error occurred";
  };

  const getSettings = async (): Promise<PluginSettings> => {
    loading.value = true;
    error.value = null;
    try {
      const response = await axios.get<ApiResponse<PluginSettings>>(
        `/api/admin/billingresourcesnewservers/settings`
      );

      if (response.data && response.data.success && response.data.data) {
        return response.data.data;
      }

      throw new Error(
        response.data?.error_message || response.data?.message || "Invalid response format"
      );
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  const updateSettings = async (data: UpdateSettingsData): Promise<PluginSettings> => {
    loading.value = true;
    error.value = null;
    try {
      const response = await axios.patch<ApiResponse<PluginSettings>>(
        `/api/admin/billingresourcesnewservers/settings`,
        data
      );

      if (response.data && response.data.success && response.data.data) {
        return response.data.data;
      }

      throw new Error(
        response.data?.error_message || response.data?.message || "Invalid response format"
      );
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
    getSettings,
    updateSettings,
  };
}

