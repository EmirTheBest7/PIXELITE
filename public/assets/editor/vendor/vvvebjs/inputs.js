/*
 * MODIFIED BY PIXELITE (Apache-2.0 section 4(b)): this file is a modified copy of VvvebJs libs/builder/inputs.js
 * from https://github.com/givanz/VvvebJs at commit 1acbab7ebfe3e7b004f1f18c039d26550fc04bd8. The changes are listed in
 * MODIFICATIONS.txt (shipped next to this file) and docs/editor/README.md in the Pixelite repository.
 * The original copyright and licence notice below is retained unchanged.
 */
/*
Copyright 2017 Ziadin Givan

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

   http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.

https://github.com/givanz/VvvebJs
*/

let Input = {
	
	init: function(name) {
	},


	onChange: function(event, node, input) {
		if (event && event.target) {
			const e = new CustomEvent('propertyChange', { detail: {value : input.value ?? this.value, input: this, origEvent:event} });
			event.currentTarget.dispatchEvent(e);
		}
	},

	renderTemplate: function(name, data) {
		return tmpl("vvveb-input-" + name, data);
	},

	setValue: function(value) {
		if (this.element[0] && value) {
			let input = this.element[0].querySelector('input');
		
			if (input) { 
				input.value = value;
			}
		}
	},
	
	render: function(name, data) {
		let html = this.renderTemplate(name, data);
		this.element = generateElements(html);
		
		//bind events
		if (this.events)
		for (let i in this.events) {
			ev = this.events[i][0];
			fun = this[ this.events[i][1] ];
			el = this.events[i][2];
		
			this.element[0].addEventListener(ev, function (ev, el, fun, target, event) {
			  if (event.target.closest(el)) {
				  //target, event, element, input
				return fun.call(event.target, event, target, this);
			  }
			}.bind(this, ev, el, fun, this.element[0]));		
		}

		return this.element[0];
	}
};

let TextInput = { ...Input, ...{

    events: [
        //event, listener, child element
        ["focusout", "onChange", "input"],
	 ],
	
	init: function(data) {
		return this.render("textinput", data);
	},
  }
}

let TextareaInput = { ...Input, ...{

    events: [
        ["keyup", "onChange", "textarea"],
	 ],
	
	setValue: function(value) {
		if (this.element[0] && value) {
			let input = this.element[0].querySelector('textarea');
		
			if (input) { 
				input.value = value;
			}
		}
	},
	
	init: function(data) {
		return this.render("textareainput", data);
	},
  }	
}

let CheckboxInput = { ...Input, ...{

    events: [
        ["change", "onCheck", "input"],
	 ],
	
	onCheck: function(event, node, input) {
		input.value = this.checked;
		return input.onChange.call(this, event, node, input);
	},

	setValue: function(value) {
		if (this.element[0]) {
			let input = this.element[0].querySelector('input');

			if (input) { 
				if (value) {
					input.checked = true;
				} else {
					input.checked = false;
				}
			}
		}
	},
	
	init: function(data) {
		return this.render("checkboxinput", data);
	},
  }
}

let SelectInput = { ...Input, ...{
	
    events: [
        ["change", "onChange", "select"],
	 ],
	

	setValue: function(value) {
		if (this.element[0] && value) {
			let input = this.element[0].querySelector('select');
		
			if (input) { 
				input.value = value;
			}
		}
	},
	
	init: function(data) {
		return this.render("select", data);
	},
  }
}

let LinkInput = { ...TextInput, ...{

    events: [
        ["change", "onChange", "input"],
	 ],
	/*
	setValue: function(value) {
		//value = value.replace(/(?<!\/)www\./, 'https://www.');
		this.element.querySelector('input').value = value;
	},
	*/
	init: function(data) {
		return this.render("textinput", data);
	},
  }
}

let DateInput = { ...TextInput, ...{

    events: [
        ["change", "onChange", "input"],
	 ],
	
	init: function(data) {
		return this.render("dateinput", data);
	},
  }
}

let RangeInput = { ...Input, ...{

    events: [
        ["change", "onRangeChange", "input"],
	 ],
	 
	onRangeChange: function(event, node, input) {
		this.parentNode.querySelector('input[type=number]').value = this.value;
		this.parentNode.querySelector('input[type=range]').value = this.value;
		return input.onChange.call(this, event, node, input);
	},	 

	setValue: function(value) {
		this.element[0].querySelector('input[type=number]').value = value;
		this.element[0].querySelector('input[type=range]').value = value;
	},
	
	init: function(data) {
		return this.render("rangeinput", data);
	},
  }
}

let NumberInput = { ...Input, ...{

    events: [
        ["change", "onChange", "input"],
	 ],
	
	init: function(data) {
		return this.render("numberinput", data);
	},
  }
}

let CssUnitInput = { ...Input, ...{

	number:0,
	unit:"px",

    events: [
        ["change", "onInputChange", "select"],
        ["change", "onInputChange", "input"],
        ["keyup", "onInputChange", "input"],
	 ],
		
	onInputChange: function(event, node, input) {
		if (node) {
			let number = node.querySelector("input").value;
			let unit = node.querySelector("select").value;

			if (this.value != "") input[this.name] = this.value;// this.name = unit or number	
			if (unit == "") unit = "px";//if unit is not set use default px
		
			let value = "";	
			if (unit == "auto")  {
				node.classList.add("auto");
				value = unit;
			}
			else  {
				node.classList.remove("auto");
				value = number + (unit ? unit : "");
			}
			
			input.value = value;

			return input.onChange.call(this, event, node, input);
		}
	},
	
	setValue: function(value) {
		if (value && this.element) {
			let element = this.element[0];
			this.number = parseFloat(value);
			this.unit = value.replace(this.number, '').trim();
			
			if (this.unit == "auto") element.classList.add("auto");

			element.querySelector("input[type=number]").value = this.number;
			element.querySelector("select").value = this.unit;
		}
	},
	
	init: function(data) {
		return this.render("cssunitinput", data);
	},
  }
}

let ColorInput = { ...Input, ...{

	 //html5 color input only supports setting values as hex colors even if the picker returns only rgb
	 rgb2hex: function(value) {
		 if (value) {
			 value = value.trim();
			 
			 if (rgb = value.match(/^rgba?[\s+]?\([\s+]?(\d+)[\s+]?,[\s+]?(\d+)[\s+]?,[\s+]?(\d+)[\s+]?/i)) {
				 
				 return (rgb && rgb.length === 4) ? "#" +
				  ("0" + parseInt(rgb[1],10).toString(16)).slice(-2) +
				  ("0" + parseInt(rgb[2],10).toString(16)).slice(-2) +
				  ("0" + parseInt(rgb[3],10).toString(16)).slice(-2) : rgb;
			 }
		}
		 
		 return value;
	},

    events: [
        ["change", "onChange", "input"],
	 ],

	setValue: function(value) {
		if (this.element[0] && value) {
			let input = this.element[0].querySelector('input');
		
			if (input) { 
				input.value = this.rgb2hex(value);
			}
		}
	},
	
	init: function(data) {
		//if no palette provided use default
		if (!data.palette) {
			data.palette = Vvveb.ColorPalette.getAll();
		}
		
		return this.render("colorinput", data);
	},
  }
}

let RadioInput = { ...Input, ...{

	events: [
        ["change", "onChange", "input"],
	 ],

	setValue: function(value) {
		if (this.element[0] && value) {
			let input = this.element[0].querySelector('input');
		
			if (input) { 
				if (value == input.value) {
					input.setAttribute("checked", "true");
					input.checked = true;
				} else {
					input.checked = false;
					input.removeAttribute("checked");
				}
				
			}
		}
	},
	
	init: function(data) {
		return this.render("radioinput", data);
	},
  }
}

let RadioButtonInput = { ...RadioInput, ...{

	setValue: function(value) {
		if (this.element[0] && value) {
			let inputs = this.element[0].querySelectorAll('input');
			inputs.forEach((el, i) => {
				if (value == el.value) {
					selected = el;
				} else {
					el.checked = false;
					el.removeAttribute("checked");
				}		
			});

			selected.checked = true;
			selected.setAttribute("checked", "checked");
		}
	},

	init: function(data) {
		return this.render("radiobuttoninput", data);
	},
  }
}

let ToggleInput = { ...Input, ...{
	events: [
        ["change", "onToggleChange", "input"],
	 ],

	onToggleChange: function(event, node, input) {
		input.value = this.checked ? this.getAttribute("data-value-on") : this.getAttribute("data-value-off");
		return input.onChange.call(this, event, node, input);
	},

	setValue: function(value) {
		if (this.element[0]) {
			let input = this.element[0].querySelector('input');

			if (input) { 
				if (value == input.getAttribute("data-value-on")) {
					input.checked = true;
					input.setAttribute("checked", true);
				} else {
					input.checked = false;
					input.removeAttribute("checked");
				}
			}
		}
	},
	
	init: function(data) {
		return this.render("toggle", data);
	},
  }
}

let ValueTextInput = { ...TextInput, ...{

    events: [
        ["focusout", "onChange", "input"],
	 ],
	
	init: function(data) {
		return this.render("textinput", data);
	},
  }
}

let GridInput = { ...Input, ...{
	

    events: [
        ["change", "onChange", "select" /*'select'*/],
        ["click", "onChange", "button" /*'select'*/],
	 ],
	

	setValue: function(value) {
		if (this.element[0] && value) {
			let input = this.element[0].querySelector('select');
		
			if (input) { 
				input.value = value;
				input.querySelector("option[selected]").selected = true;
			}
		}
	},
	
	init: function(data) {
		return this.render("grid", data);
	},
  }
}

let TextValueInput = { ...Input, ...{
	

    events: [
        ["focusout", "onChange", "input"],
	    ["click", "onChange", "button" /*'select'*/],
	 ],
	
	setValue: function(value) {
		return false;
	},
		
	init: function(data) {
		return this.render("textvalue", data);
	},
  }
}

let ButtonInput = { ...Input, ...{

    events: [
        ["click", "onChange", "button" /*'select'*/],
	 ],
	

	setValue: function(value) {
		if (this.element[0] && value) {
			let input = this.element[0].querySelector('button');
		
			if (input) { 
				input.value = value;
			}
		}		
	},
	
	init: function(data) {
		return this.render("button", data);
	},
  }
}

let SectionInput = { ...Input, ...{

    events: [
        //["click", "onChange", "button" /*'select'*/],
	 ],
	

	setValue: function(value) {
		return false;
	},
	
	init: function(data) {
		return this.render("sectioninput", data);
	},
  }
}

let ListInput = { ...Input, ...{
	
    events: [
        ["change", "onChange", "select"],
        ["click", "remove", ".delete-btn"],
        ["click", "add", ".btn-new"],
        ["click", "select", ".section-item"],
	 ],
	

	remove: function(event, node, input) {
		let sectionItem = this.closest(".section-item");
		let index = [...sectionItem.parentNode.children].indexOf(sectionItem);//sectionItem.index();
		let data = input.data;
		
		if (data.removeElement) {
			let container = input.node;
			if (data.container) {
				container.querySelector(data.container);
			}
			container.querySelector(data.selector + ":nth-child(" + (index + 1) + ")").remove();
		}
		sectionItem.remove();
		
		event.action = "remove";
		event.index = index;
		input.onChange(event, node, input, this);
		event.preventDefault();
		return false;
	},

	add: function(event, node, input) {
		let newElement = input.data.newElement ?? false;
		if (newElement) {
			let container = input.node;
			if (data.container) {
				container.querySelector(data.container);
			}			
			container.append(generateElements(newElement)[0]);
		}
		
		event.action = "add";
		input.onChange(event, node, input, this);
		return false;
	},	
	
	select: function(event, node, input) {
		let sectionItem = this.closest(".section-item");
		if (sectionItem.parentNode) {
			let index = [...sectionItem.parentNode.children].indexOf(sectionItem);//sectionItem.index();
			
			event.action = "select";
			event.index = index;
			input.onChange(event, node, input, this);
		}
		return false;
	},

	setValue: function(value) {
	},

	init: function(data, node) {
		this.component = data.component;
		this.selector = data.selector;
		this.node = node;

		let elements = this.node.querySelectorAll(data.container + " " + this.selector);
		let options = [];
		
		elements.forEach(function (e, i) {
			let element = e;
			if (data.nameElement) {
				element = element.querySelector(data.nameElement);
			}
			let name = (data.name = "text" ? element.textContent.substr(0, 15) : element.id);
			options.push({
				name: name,
				type: (data.prefix ?? "") + (i + 1) + (data.suffix ?? ""),
			});
		});

		data.options = options;
		data.elements = elements;
		this.data = data;

		let list = this.render("listinput", data);
		let items = list.querySelectorAll(".section-item");
		let i = 0;
		for (const item of items) {
			const container = item.querySelector(".tree") ?? item;
			let properties = {};
			if (data.elementProperties) {
				for (let prop in data.elementProperties) {
					properties[prop] = Object.assign({}, data.elementProperties[prop]);
					properties[prop].element = elements[i];
				}
			}
			
			Vvveb.Components.renderProperties(null, properties, null, container);
			i++;
		}

		return list;
	},
  }
}

let NoticeInput = { ...Input, ...{

    events: [
	 ],
	
	init: function(data) {
		return this.render("noticeinput", data);
	},
  }
}

// ---- PIXELITE replacements (original code, not from VvvebJs) -------------------------------------------------------------------------------
// ImageInput: upstream posts files to a sample upload.php through jQuery. Here the picture is chosen from (or uploaded into) the project's own media
// library by the host page: Vvveb.MediaPicker.open(callback) is provided by px-builder.js.
let ImageInput = { ...Input, ...{
	events: [
		["click", "onPick", "button[data-pick]"],
		["click", "onClear", "button[data-clear]"],
	],
	setValue: function(value) {
		const root = this.element[0];
		if (!root) return;
		const img = root.querySelector("img"), text = root.querySelector("input[type=text]");
		if (text) text.value = value || "";
		if (img) { if (value) img.setAttribute("src", value); else img.removeAttribute("src"); }
	},
	onPick: function(event, node, input) {
		const root = node;
		Vvveb.MediaPicker.open((url) => {
			input.setValue.call({element: [root]}, url);
			root.dispatchEvent(new CustomEvent("propertyChange", {detail: {value: url, input: input, origEvent: event}}));
		});
		event.preventDefault();
	},
	onClear: function(event, node, input) {
		input.setValue.call({element: [node]}, "");
		node.dispatchEvent(new CustomEvent("propertyChange", {detail: {value: "", input: input, origEvent: event}}));
		event.preventDefault();
	},
	init: function(data) {
		return this.render("imageinput", data);
	},
}};

// TagsInput (classes): upstream needs the autocomplete library; a plain space-separated text field does the same job.
let TagsInput = { ...TextInput, ...{
	init: function(data) {
		return this.render("textinput", data);
	},
}};
