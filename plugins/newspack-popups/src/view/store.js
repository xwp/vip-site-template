const STORE = {};

export const set = ( key, value ) => {
	STORE[ key ] = value;
};

export const get = key => STORE[ key ];
