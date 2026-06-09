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

export interface Location {
  id: number;
  name: string;
  description?: string;
  flag_code?: string;
  allowed?: boolean;
  error_message?: string | null;
}

export interface Node {
  id: number;
  name: string;
  location_id: number;
  fqdn: string;
  maintenance_mode: boolean;
  allowed?: boolean;
  error_message?: string | null;
  server_count?: number;
  max_servers_per_node?: number;
  at_capacity?: boolean;
}

export interface Realm {
  id: number;
  name: string;
  description?: string;
  logo?: string;
  allowed?: boolean;
  error_message?: string | null;
}

export interface Spell {
  id: number;
  name: string;
  description?: string;
  banner?: string;
  realm_id: number;
  allowed?: boolean;
  error_message?: string | null;
}

export interface Allocation {
  id: number;
  ip: string;
  port: number;
  ip_alias?: string;
  node_id: number;
}

export interface AvailableResources {
  memory_limit: number;
  cpu_limit: number;
  disk_limit: number;
  server_limit: number;
  database_limit: number;
  backup_limit: number;
  allocation_limit: number;
}

export interface MinimumResources {
  memory: number;
  cpu: number;
  disk: number;
}

export type ResourceFieldMode = "user" | "fixed" | "hidden";

export interface ResourceFieldPolicyRow {
  mode: ResourceFieldMode;
  value?: number;
  default?: number;
}

export type ResourceFieldPolicies = Record<string, ResourceFieldPolicyRow>;

export type PlacementAutoStrategy = "first" | "least_capacity";

export type PlacementPolicyValue = number | PlacementAutoStrategy;

export interface PlacementFieldPolicyRow {
  mode: ResourceFieldMode;
  value?: PlacementPolicyValue;
  default?: PlacementPolicyValue;
}

export type PlacementFieldPolicies = Record<string, PlacementFieldPolicyRow>;

export interface ServerCreationOptions {
  locations: Location[];
  nodes: Node[];
  realms: Realm[];
  spells: Spell[];
  available_resources: AvailableResources;
  minimum_resources: MinimumResources;
  resource_field_policies?: ResourceFieldPolicies;
  placement_field_policies?: PlacementFieldPolicies;
  placement_resolved_defaults?: Partial<
    Record<"location" | "node" | "realm" | "spell", number>
  >;
  /** 0 = unlimited */
  max_servers_per_node?: number;
}

export interface CreateServerData {
  name: string;
  location_id?: number;
  node_id: number;
  realms_id: number;
  spell_id: number;
  allocation_id?: number;
  memory: number;
  cpu: number;
  disk: number;
  swap?: number;
  io?: number;
  description?: string;
  startup: string;
  image: string;
  database_limit?: number;
  allocation_limit?: number;
  backup_limit?: number;
  variables?: Record<string, string>;
}

export function useNewServerAPI() {
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

  const getOptions = async (): Promise<ServerCreationOptions> => {
    loading.value = true;
    error.value = null;
    try {
      const response = await axios.get<ApiResponse<ServerCreationOptions>>(
        `/api/user/billingresourcesnewservers/options`
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

  const getSpellDetails = async (spellId: number): Promise<{
    id: number;
    name: string;
    description?: string;
    startup?: string;
    docker_images?: string;
    docker_image?: string;
  }> => {
    loading.value = true;
    error.value = null;
    try {
      const response = await axios.get<ApiResponse<{
        spell: {
          id: number;
          name: string;
          description?: string;
          startup?: string;
          docker_images?: string;
          docker_image?: string;
        };
      }>>(`/api/user/billingresourcesnewservers/spells/${spellId}`);

      if (response.data && response.data.success && response.data.data) {
        return response.data.data.spell;
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

  const getAllocations = async (nodeId: number): Promise<Allocation[]> => {
    loading.value = true;
    error.value = null;
    try {
      const response = await axios.get<ApiResponse<{ allocations: Allocation[] }>>(
        `/api/user/billingresourcesnewservers/allocations?node_id=${nodeId}`
      );

      if (response.data && response.data.success && response.data.data) {
        return response.data.data.allocations || [];
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

  const createServer = async (data: CreateServerData): Promise<any> => {
    loading.value = true;
    error.value = null;
    try {
      const response = await axios.post<ApiResponse<any>>(
        `/api/user/billingresourcesnewservers/servers`,
        data
      );

      if (response.data && response.data.success) {
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
    getOptions,
    getSpellDetails,
    getAllocations,
    createServer,
  };
}

