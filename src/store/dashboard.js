/*
 * @copyright Copyright (c) 2020 Jakob Röhrl <jakob.roehrl@web.de>
 *
 * @author Jakob Röhrl <jakob.roehrl@web.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import Vue from 'vue'
import Vuex from 'vuex'
import { CardApi } from '../services/CardApi'
Vue.use(Vuex)

const apiClient = new CardApi()
export default new Vuex.Store({
	state: {
		withDue: [],
	},
	getters: {
		dashboards: state => {
			return state.withDue
		},
	},
	mutations: {
		setDashboards(state, withDue) {
			state.withDue = withDue
		},

	},
	actions: {
		async loadDashboards({ commit }) {
			const withDue = await apiClient.findAllWithDue()
			commit('setDashboards', withDue)
		},
	},
})
