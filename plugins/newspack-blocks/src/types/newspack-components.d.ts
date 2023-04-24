declare module 'newspack-components' {
	function useObjectState<StateObject>(
		object: StateObject
	): [object: StateObject, (object: Partial<StateObject>) => void];
	const hooks = { useObjectState };
	export { hooks };
}
